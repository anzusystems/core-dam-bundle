<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AudioAttributes
{
    #[ORM\Column(type: Types::FLOAT)]
    private int $duration;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $codecName;

    #[ORM\Column(type: Types::INTEGER)]
    private int $bitrate;

    public function __construct()
    {
        $this->setDuration(0);
        $this->setCodecName('');
        $this->setBitrate(0);
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getCodecName(): string
    {
        return $this->codecName;
    }

    public function setCodecName(string $codecName): self
    {
        $this->codecName = $codecName;

        return $this;
    }

    public function getBitrate(): int
    {
        return $this->bitrate;
    }

    public function setBitrate(int $bitrate): self
    {
        $this->bitrate = $bitrate;

        return $this;
    }
}
