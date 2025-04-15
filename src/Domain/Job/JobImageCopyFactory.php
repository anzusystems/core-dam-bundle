<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestItemDto;
use Doctrine\Common\Collections\Collection;

final readonly class JobImageCopyFactory
{
    /**
     * @param Collection<int|string, Asset> $assets
     */
    public function createPodcastSynchronizerJob(AssetLicence $licence, Collection $assets): JobImageCopy
    {
        return (new JobImageCopy())
            ->setLicence($licence)
            ->setItems(
                $assets->map(
                    fn (Asset $asset): JobImageCopyItem => (new JobImageCopyItem())->setSourceAssetId((string) $asset->getId())
                )
            )
        ;
    }

    public function createFromCopyList(JobImageCopyRequestDto $copyDto, bool $allowExtSystemCallback = true): JobImageCopy
    {
        return (new JobImageCopy())
            ->setLicence($copyDto->getTargetAssetLicence())
            ->setAllowExtSystemCallback($allowExtSystemCallback)
            ->setItems(
                $copyDto->getItems()->map(
                    fn (JobImageCopyRequestItemDto $itemDto): JobImageCopyItem => (new JobImageCopyItem())->setSourceAssetId((string) $itemDto->getImageFile()->getAsset()->getId())
                )
            )
        ;
    }
}
