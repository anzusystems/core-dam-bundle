<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ElevenlabsVoiceSettingsDto
{
    #[Serialize]
    private float $stability = 0.0;

    #[Serialize(serializedName: 'similarity_boost')]
    private float $similarityBoost = 0.0;

    public function getStability(): float
    {
        return $this->stability;
    }

    public function setStability(float $stability): self
    {
        $this->stability = $stability;

        return $this;
    }

    public function getSimilarityBoost(): float
    {
        return $this->similarityBoost;
    }

    public function setSimilarityBoost(float $similarityBoost): self
    {
        $this->similarityBoost = $similarityBoost;

        return $this;
    }
}
