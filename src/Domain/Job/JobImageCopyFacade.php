<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;

final readonly class JobImageCopyFacade
{
    public function __construct(
        private JobImageCopyManager $manager,
        private Validator $validator,
    ) {
    }

    public function create(JobImageCopy $job): JobInterface
    {
        $this->validator->validate($job);
        $this->manager->create($job);

        return $job;
    }
}
