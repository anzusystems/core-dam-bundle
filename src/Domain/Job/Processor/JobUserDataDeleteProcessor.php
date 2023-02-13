<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;

final class JobUserDataDeleteProcessor extends AbstractJobProcessor
{
    public static function getSupportedJob(): string
    {
        return JobUserDataDelete::class;
    }

    public function process(JobInterface $job): void
    {
    }
}
