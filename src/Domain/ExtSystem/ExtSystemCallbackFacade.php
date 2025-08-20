<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
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

    public function notifyAssetsChanged(Collection $assets): bool
    {
        if ($assets->isEmpty()) {
            return false;
        }

        // Group assets by external system slug
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
