<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Entity\Traits\LicenceBulkJobTrait;
use AnzuSystems\CoreDamBundle\Repository\JobAssetFileReprocessInternalFlagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobAssetFileReprocessInternalFlagRepository::class)]
class JobAssetFileReprocessInternalFlag extends Job
{
    use LicenceBulkJobTrait;
}
