<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Distribution;

use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class DistributionAdmCreateDto extends AbstractEntityDto
{
    private string $type;
    private Collection $blockedBy;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData = [];

    public function __construct()
    {
        $this->setType('');
        $this->setBlockedBy(new ArrayCollection());
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getBlockedBy(): Collection
    {
        return $this->blockedBy;
    }

    public function setBlockedBy(Collection $blockedBy): self
    {
        $this->blockedBy = $blockedBy;

        return $this;
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function setCustomData(array $customData): self
    {
        $this->customData = $customData;

        return $this;
    }
}
