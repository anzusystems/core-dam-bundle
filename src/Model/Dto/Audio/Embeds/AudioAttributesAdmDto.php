<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioAttributes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AudioAttributesAdmDto
{
    private int $duration;
    private string $codecName;
    private int $bitrate;

    public static function getInstance(AudioAttributes $attributes): self
    {
        return (new self())
            ->setDuration($attributes->getDuration())
            ->setCodecName($attributes->getCodecName())
            ->setBitrate($attributes->getBitrate());
    }

    #[Serialize]
    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    #[Serialize]
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
