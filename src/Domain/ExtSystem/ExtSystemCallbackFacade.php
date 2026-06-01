<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Throwable;

final class ExtSystemCallbackFacade
{
    private ServiceLocator $extSystemCallbackLocator;

    public function __construct(
        #[AutowireLocator(ExtSystemCallbackInterface::class, indexAttribute: 'key')]
        ServiceLocator $extSystemCallbackLocator,
        private readonly DamLogger $logger,
        private readonly ExtSystemRepository $extSystemRepository,
    ) {
        $this->extSystemCallbackLocator = $extSystemCallbackLocator;
    }

    public function notifyFinishedJobImageCopy(JobImageCopy $jobImageCopy): void
    {
        $this->getCallback($jobImageCopy->getLicence()->getExtSystem()->getSlug())?->notifyFinishedJobImageCopy($jobImageCopy);
    }

    public function isImageFileUsed(ImageFile $imageFile): bool
    {
        return $this->getCallback($imageFile->getLicence()->getExtSystem()->getSlug())?->isImageFileUsed($imageFile) ?? false;
    }

    /**
     * @param Collection<array-key, Asset> $assets
     */
    public function notifyAssetsChanged(Collection $assets): bool
    {
        if ($assets->isEmpty()) {
            return false;
        }

        /** @var array<string, Asset[]> $grouped */
        $grouped = [];
        foreach ($assets as $asset) {
            $slug = $asset->getExtSystem()->getSlug();
            if (false === isset($grouped[$slug])) {
                $grouped[$slug] = [];
            }
            $grouped[$slug][] = $asset;
        }

        $processed = false;
        foreach ($grouped as $slug => $assetsForSlug) {
            $callback = $this->getCallback($slug);
            if (null === $callback) {
                continue;
            }

            $callback->notifyAssetsChanged(new ArrayCollection($assetsForSlug));
            $processed = true;
        }

        return $processed;
    }

    /**
     * @param Collection<array-key, ImageFile> $images
     */
    public function notifyImagesChanged(Collection $images): bool
    {
        if ($images->isEmpty()) {
            return false;
        }

        /** @var array<string, ImageFile[]> $grouped */
        $grouped = [];
        foreach ($images as $imageFile) {
            $slug = $imageFile->getLicence()->getExtSystem()->getSlug();
            if (false === isset($grouped[$slug])) {
                $grouped[$slug] = [];
            }
            $grouped[$slug][] = $imageFile;
        }

        $processed = false;
        foreach ($grouped as $slug => $imagesForSlug) {
            $callback = $this->getCallback($slug);
            if (null === $callback) {
                continue;
            }

            $callback->notifyImagesChanged(new ArrayCollection($imagesForSlug));
            $processed = true;
        }

        return $processed;
    }

    public function notifyMediaStatus(
        int $extSystemId,
        string $extResourceName,
        string $extId,
        string $assetId,
        MediaStatusType $status,
        ?string $failureReason,
        bool $initial,
    ): void {
        $extSystem = $this->extSystemRepository->find($extSystemId);
        if (null === $extSystem) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'extSystemCallback.notifyMediaStatus.extSystemNotFound', [
                'extSystemId' => $extSystemId,
                'extResourceName' => $extResourceName,
                'extId' => $extId,
            ]);

            return;
        }

        $this->getCallback($extSystem->getSlug())?->notifyMediaStatus($extResourceName, $extId, $assetId, $status, $failureReason, $initial);
    }

    private function getCallback(string $slug): ?ExtSystemCallbackInterface
    {
        try {
            return $this->extSystemCallbackLocator->get($slug);
        } catch (Throwable $e) {
            $this->logger->warning('ExtSystemCallback', $e->getMessage());

            return null;
        }
    }
}
