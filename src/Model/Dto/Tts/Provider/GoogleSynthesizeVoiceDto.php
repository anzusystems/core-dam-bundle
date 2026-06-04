<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\CoreDamBundle\Model\Enum\GoogleSsmlGender;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class GoogleSynthesizeVoiceDto
{
    #[Serialize]
    private string $languageCode = '';

    #[Serialize]
    private string $name = '';

    #[Serialize]
    private GoogleSsmlGender $ssmlGender = GoogleSsmlGender::Neutral;

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(string $languageCode): self
    {
        $this->languageCode = $languageCode;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSsmlGender(): GoogleSsmlGender
    {
        return $this->ssmlGender;
    }

    public function setSsmlGender(GoogleSsmlGender $ssmlGender): self
    {
        $this->ssmlGender = $ssmlGender;

        return $this;
    }
}
