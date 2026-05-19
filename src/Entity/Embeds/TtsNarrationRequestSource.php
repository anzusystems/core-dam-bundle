<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The narrated text payload + its SHA-256 hash (the latter is the regen-dedupe key on TtsAsset).
 */
#[ORM\Embeddable]
class TtsNarrationRequestSource
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $text;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $hash;

    public function __construct()
    {
        $this->setText(null);
        $this->setHash(null);
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }
}
