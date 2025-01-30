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

    public function isCanBeCurrentAuthor(): bool
    {
        return $this->canBeCurrentAuthor;
    }

    public function setCanBeCurrentAuthor(bool $canBeCurrentAuthor): self
    {
        $this->canBeCurrentAuthor = $canBeCurrentAuthor;

        return $this;
    }
}
