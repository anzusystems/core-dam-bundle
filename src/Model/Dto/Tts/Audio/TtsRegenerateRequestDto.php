<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class TtsRegenerateRequestDto
{
    #[Serialize]
    #[Assert\Regex(pattern: VoiceFamily::SLUG_REGEX, message: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Length(max: VoiceFamily::SLUG_MAX_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $voiceFamilySlug = null;

    public function getVoiceFamilySlug(): ?string
    {
        return $this->voiceFamilySlug;
    }

    public function setVoiceFamilySlug(?string $voiceFamilySlug): self
    {
        $this->voiceFamilySlug = $voiceFamilySlug;

        return $this;
    }
}
