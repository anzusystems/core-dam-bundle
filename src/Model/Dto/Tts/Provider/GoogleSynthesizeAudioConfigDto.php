<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class GoogleSynthesizeAudioConfigDto
{
    #[Serialize]
    private string $audioEncoding = 'MP3';

    #[Serialize]
    private float $speakingRate = 1.0;

    #[Serialize]
    private float $pitch = 0.0;

    public function getAudioEncoding(): string
    {
        return $this->audioEncoding;
    }

    public function setAudioEncoding(string $audioEncoding): self
    {
        $this->audioEncoding = $audioEncoding;

        return $this;
    }

    public function getSpeakingRate(): float
    {
        return $this->speakingRate;
    }

    public function setSpeakingRate(float $speakingRate): self
    {
        $this->speakingRate = $speakingRate;

        return $this;
    }

    public function getPitch(): float
    {
        return $this->pitch;
    }

    public function setPitch(float $pitch): self
    {
        $this->pitch = $pitch;

        return $this;
    }
}
