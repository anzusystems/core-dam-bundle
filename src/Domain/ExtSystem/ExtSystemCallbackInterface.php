<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ExtSystemCallbackInterface
{
    public static function getDefaultKeyName(): string;

    public function notifyFinishedJobImageCopy(JobImageCopy $jobImageCopy): void;

    public function isImageFileUsed(ImageFile $imageFile): bool;

    /**
     * @param Collection<array-key, Asset> $collection
     */
    public function notifyAssetsChanged(Collection $collection): void;

    /**
     * @param Collection<array-key, ImageFile> $collection
     */
    public function notifyImagesChanged(Collection $collection): void;

    /**
     * Reports an out-of-band media outcome (e.g. a generation failure) that can't be expressed as positive
     * current-state on {@see notifyAssetsChanged()}. Generic side-channel — extend via {@see MediaStatusType}.
     * The ext-system correlates the affected media by {@see $assetId} and decides any cleanup from its own
     * media state (e.g. drop a not-yet-playable placeholder, keep a playable one).
     *
     * @param string $assetId the asset this operation targeted (reserved id for Initial, stable id for
     *                         Regenerate) — lets the ext-system correlate which media the status pertains to
     */
    public function notifyMediaStatus(
        string $extResourceName,
        string $extId,
        string $assetId,
        MediaStatusType $status,
        ?string $failureReason,
    ): void;
}
