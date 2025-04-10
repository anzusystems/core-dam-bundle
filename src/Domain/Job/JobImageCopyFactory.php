<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyItemDto;
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

    public function createFromCopyList(JobImageCopyDto $copyDto, bool $allowExtSystemCallback = true): JobImageCopy
    {
        return (new JobImageCopy())
            ->setLicence($copyDto->getTargetAssetLicence())
            ->setAllowExtSystemCallback($allowExtSystemCallback)
            ->setItems(
                $copyDto->getItems()->map(
                    fn (JobImageCopyItemDto $itemDto): JobImageCopyItem =>
                    (new JobImageCopyItem())->setSourceAssetId((string) $itemDto->getImageFile()->getAsset()->getId())
                )
            )
        ;
    }
}
