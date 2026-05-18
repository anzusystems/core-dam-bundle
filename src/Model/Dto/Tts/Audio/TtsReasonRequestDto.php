<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shared payload for TTS mutating actions that take a free-text reason (cancel job, unpublish, ...).
 */
final class TtsReasonRequestDto
{
    #[Serialize]
    #[Assert\Length(max: 1_000, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $reason = null;

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }
}
