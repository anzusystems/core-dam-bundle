<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DistributionDataUrl
{
    private const string TYPE_KEY = 'url';

    #[Serialize]
    public ?string $value = null;

    #[Serialize]
    public function getType(): string
    {
        return self::TYPE_KEY;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
