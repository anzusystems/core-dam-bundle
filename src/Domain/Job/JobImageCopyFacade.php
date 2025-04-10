<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyDto;
use Doctrine\Common\Collections\Collection;

final readonly class JobImageCopyFacade
{
    public function __construct(
        private JobImageCopyManager $manager,
        private Validator $validator,
        private JobImageCopyFactory $imageCopyFactory,
    ) {
    }

    public function create(JobImageCopy $job): JobInterface
    {
        $this->validator->validate($job);
        $this->manager->create($job);

        return $job;
    }

    public function createPodcastSynchronizerJob(AssetLicence $licence, Collection $assets): JobImageCopy
    {
        $job = $this->imageCopyFactory->createPodcastSynchronizerJob($licence, $assets);
        $this->manager->create($job);

        return $job;
    }

    public function createFromCopyList(JobImageCopyDto $dto): JobImageCopy
    {
        $this->validator->validate($dto);
        $job = $this->imageCopyFactory->createFromCopyList($dto);

        $this->manager->create($job);

        return $job;
    }
}
