<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Traits\ReviewedTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class AuthorFlags
{
    use ReviewedTrait;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Serialize]
    private bool $canBeCurrentAuthor = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $source = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $hiddenInCredit = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $singleUseEnforced = false;

    public function isCanBeCurrentAuthor(): bool
    {
        return $this->canBeCurrentAuthor;
    }

    public function setCanBeCurrentAuthor(bool $canBeCurrentAuthor): self
    {
        $this->canBeCurrentAuthor = $canBeCurrentAuthor;

        return $this;
    }

    public function isSource(): bool
    {
        return $this->source;
    }

    public function setSource(bool $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function isHiddenInCredit(): bool
    {
        return $this->hiddenInCredit;
    }

    public function setHiddenInCredit(bool $hiddenInCredit): self
    {
        $this->hiddenInCredit = $hiddenInCredit;

        return $this;
    }

    public function isSingleUseEnforced(): bool
    {
        return $this->singleUseEnforced;
    }

    public function setSingleUseEnforced(bool $singleUseEnforced): self
    {
        $this->singleUseEnforced = $singleUseEnforced;

        return $this;
    }
}
