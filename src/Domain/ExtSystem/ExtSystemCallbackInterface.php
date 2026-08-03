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
     * @param iterable<ImageFile> $imageFiles
     *
     * @return array<string, bool> image file id => used
     */
    public function isImageFileUsedBulk(iterable $imageFiles): array;

    /**
     * @param Collection<array-key, Asset> $collection
     */
    public function notifyAssetsChanged(Collection $collection): void;

    /**
     * @param Collection<array-key, ImageFile> $collection
     */
    public function notifyImagesChanged(Collection $collection): void;

    /**
     * Out-of-band media status callback (e.g. generation failure); extend via {@see MediaStatusType}.
     */
    public function notifyMediaStatus(
        string $assetId,
        MediaStatusType $status,
        ?string $failureReason,
    ): void;
}
