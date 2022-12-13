<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use DomainException as BaseDomainException;

class DistributionFailedException extends BaseDomainException
{
    public function __construct(
        private readonly DistributionFailReason $failReason = DistributionFailReason::Unknown,
    ) {
        parent::__construct();
    }

    public function getFailReason(): DistributionFailReason
    {
        return $this->failReason;
    }
}
