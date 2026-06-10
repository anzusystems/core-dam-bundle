<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * Google Cloud TTS `text:synthesize` request body.
 */
final class GoogleSynthesizeRequestDto
{
    #[Serialize]
    private GoogleSynthesizeInputDto $input;

    #[Serialize]
    private GoogleSynthesizeVoiceDto $voice;

    #[Serialize]
    private GoogleSynthesizeAudioConfigDto $audioConfig;

    public function __construct()
    {
        $this->input = new GoogleSynthesizeInputDto();
        $this->voice = new GoogleSynthesizeVoiceDto();
        $this->audioConfig = new GoogleSynthesizeAudioConfigDto();
    }

    public function getInput(): GoogleSynthesizeInputDto
    {
        return $this->input;
    }

    public function getVoice(): GoogleSynthesizeVoiceDto
    {
        return $this->voice;
    }

    public function getAudioConfig(): GoogleSynthesizeAudioConfigDto
    {
        return $this->audioConfig;
    }
}
