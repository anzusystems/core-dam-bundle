<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopyItem;
use Doctrine\Common\Collections\Collection;

final readonly class JobImageCopyFactory
{
    public function __construct(
        private JobImageCopyFacade $imageCopyFacade,
    ) {
    }

    /**
     * @param Collection<int|string, Asset> $assets
     */
    public function createPodcastSynchronizerJob(AssetLicence $licence, Collection $assets): JobImageCopy
    {
        $job = (new JobImageCopy())
            ->setLicence($licence)
            ->setItems(
                $assets->map(
                    fn (Asset $asset): JobImageCopyItem => (new JobImageCopyItem())->setSourceAsset($asset)
                )
            )
        ;

        $this->imageCopyFacade->create($job);

        return $job;
    }
}
