<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\DistributionUpdateHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class DistributionUpdateCollection
{
    #[Serialize(handler: DistributionUpdateHandler::class)]
    private array $distributions = [];

    /**
     * @return array<int,
     */
    public function getDistributions(): array
    {
        return $this->distributions;
    }

    public function setDistributions(array $distributions): self
    {
        $this->distributions = $distributions;
        return $this;
    }
}
