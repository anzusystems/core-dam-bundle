<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
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
        return $this->isImageFileUsedBulk([$imageFile])[(string) $imageFile->getId()] ?? true;
    }

    /**
     * Fails closed: images whose ext system has no registered (or a failing) callback,
     * or that are missing from the callback's response, are treated as "used".
     * Total map: every id in $imageFiles is present in the result.
     *
     * @param iterable<ImageFile> $imageFiles
     *
     * @return array<string, bool> image file id => used
     */
    public function isImageFileUsedBulk(iterable $imageFiles): array
    {
        $grouped = CollectionHelper::groupBy(
            $imageFiles,
            static fn (ImageFile $imageFile): string => $imageFile->getLicence()->getExtSystem()->getSlug(),
        );

        $result = [];
        foreach ($grouped as $slug => $imagesForSlug) {
            $usageMap = $this->resolveBulkUsage($slug, $imagesForSlug);
            foreach ($imagesForSlug as $imageFile) {
                $id = (string) $imageFile->getId();
                $result[$id] = $usageMap[$id] ?? true;
            }
        }

        return $result;
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

    public function notifyAssetChanged(Asset $asset): bool
    {
        return $this->notifyAssetsChanged(new ArrayCollection([$asset]));
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
        string $assetId,
        MediaStatusType $status,
        ?string $failureReason,
    ): void {
        $extSystem = $this->extSystemRepository->find($extSystemId);
        if (null === $extSystem) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'extSystemCallback.notifyMediaStatus.extSystemNotFound', [
                'extSystemId' => $extSystemId,
                'assetId' => $assetId,
            ]);

            return;
        }

        $callback = $this->getCallback($extSystem->getSlug());
        if (null === $callback) {
            $this->logger->warning(DamLogger::NAMESPACE_TTS, 'extSystemCallback.notifyMediaStatus.noCallbackRegistered', [
                'extSystemId' => $extSystemId,
                'slug' => $extSystem->getSlug(),
                'assetId' => $assetId,
            ]);

            return;
        }

        $callback->notifyMediaStatus($assetId, $status, $failureReason);
    }

    /**
     * @param ImageFile[] $imageFiles
     *
     * @return array<string, bool>
     */
    private function resolveBulkUsage(string $slug, array $imageFiles): array
    {
        $callback = $this->getCallback($slug);
        if (null === $callback) {
            $this->logger->warning(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                'isImageFileUsedBulk.noCallbackRegistered',
                ['slug' => $slug],
            );

            return [];
        }

        try {
            return $callback->isImageFileUsedBulk($imageFiles);
        } catch (Throwable $e) {
            $this->logger->error(
                DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK,
                sprintf('isImageFileUsedBulk failed for ext system (%s)', $slug),
                exception: $e,
            );

            return [];
        }
    }

    private function getCallback(string $slug): ?ExtSystemCallbackInterface
    {
        try {
            return $this->extSystemCallbackLocator->get($slug);
        } catch (Throwable $e) {
            $this->logger->warning(DamLogger::NAMESPACE_EXT_SYSTEM_CALLBACK, $e->getMessage());

            return null;
        }
    }
}
