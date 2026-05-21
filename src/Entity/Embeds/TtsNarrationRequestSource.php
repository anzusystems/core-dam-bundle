<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Narrated text payload. Nullified at terminal status by {@see TtsNarrationRequestManager::finalize};
 * audit copy lives on {@see \AnzuSystems\CoreDamBundle\Entity\TtsAsset::$sourceTextSnapshot}.
 */
#[ORM\Embeddable]
class TtsNarrationRequestSource
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $text;

    public function __construct()
    {
        $this->setText(null);
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
}
