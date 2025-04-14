<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use Doctrine\Common\Collections\Collection;

final readonly class JobImageCopyFacade
{
    public function __construct(
        private JobImageCopyManager $manager,
        private Validator $validator,
        private JobImageCopyFactory $imageCopyFactory,
    ) {
    }

    /**
     * @throws ValidationException
     */
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

    public function createFromCopyList(JobImageCopyRequestDto $dto): JobImageCopy
    {
        $this->validator->validate($dto);
        $job = $this->imageCopyFactory->createFromCopyList($dto);

        $this->manager->create($job);

        return $job;
    }
}
