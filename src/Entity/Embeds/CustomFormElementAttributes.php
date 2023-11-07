<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class CustomFormElementAttributes
{
    #[ORM\Column(enumType: CustomFormElementType::class)]
    #[Serialize]
    private CustomFormElementType $type;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serialize]
    private ?int $minValue;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serialize]
    private ?int $maxValue;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serialize]
    private ?int $minCount;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serialize]
    private ?int $maxCount;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $required;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $searchable;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $readonly;

    public function __construct()
    {
        $this->setType(CustomFormElementType::Default);
        $this->setMaxValue(null);
        $this->setMinValue(null);
        $this->setMinCount(null);
        $this->setMaxCount(null);
        $this->setRequired(false);
        $this->setSearchable(false);
        $this->setReadonly(false);
    }

    public function getType(): CustomFormElementType
    {
        return $this->type;
    }

    public function setType(CustomFormElementType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getMinValue(): ?int
    {
        return $this->minValue;
    }

    public function setMinValue(?int $minValue): self
    {
        $this->minValue = $minValue;

        return $this;
    }

    public function getMaxValue(): ?int
    {
        return $this->maxValue;
    }

    public function setMaxValue(?int $maxValue): self
    {
        $this->maxValue = $maxValue;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getMinCount(): ?int
    {
        return $this->minCount;
    }

    public function setMinCount(?int $minCount): self
    {
        $this->minCount = $minCount;

        return $this;
    }

    public function getMaxCount(): ?int
    {
        return $this->maxCount;
    }

    public function setMaxCount(?int $maxCount): self
    {
        $this->maxCount = $maxCount;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function setReadonly(bool $readonly): self
    {
        $this->readonly = $readonly;
        return $this;
    }
}
