<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
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
     * @param string $assetId the asset this generation targeted (reserved id for Initial, stable id for
     *                         Regenerate) — lets the ext-system correlate which media the failure pertains to
     * @param bool   $initial  true = Initial generation (ext-system may drop its placeholder media);
     *                         false = Regenerate (the previously-generated media must be kept)
     */
    public function notifyAudioNarrationFailed(
        string $extResourceName,
        string $extId,
        string $failureReason,
        string $assetId,
        bool $initial,
    ): void;
}
