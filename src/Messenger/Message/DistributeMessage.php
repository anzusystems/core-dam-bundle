<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Messenger\Message;

use AnzuSystems\CoreDamBundle\Entity\Distribution;

final class DistributeMessage
{
    private string $distributionId;

    public function __construct(
        Distribution $distribution,
    ) {
        $this->distributionId = (string) $distribution->getId();
    }

    public function getDistributionId(): string
    {
        return $this->distributionId;
    }
}
