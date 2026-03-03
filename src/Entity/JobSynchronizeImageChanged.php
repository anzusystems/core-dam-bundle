<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Entity\Traits\LicenceBulkJobTrait;
use AnzuSystems\CoreDamBundle\Repository\JobSynchronizeImageChangedRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobSynchronizeImageChangedRepository::class)]
class JobSynchronizeImageChanged extends Job
{
    use LicenceBulkJobTrait;
}
