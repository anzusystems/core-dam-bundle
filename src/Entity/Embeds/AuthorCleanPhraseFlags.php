<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AuthorCleanPhraseFlags
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $wordBoundary = false;
    public function __construct()
    {
        $this->setWordBoundary(false);
    }

    public function isWordBoundary(): bool
    {
        return $this->wordBoundary;
    }

    public function setWordBoundary(bool $wordBoundary): self
    {
        $this->wordBoundary = $wordBoundary;

        return $this;
    }
}
