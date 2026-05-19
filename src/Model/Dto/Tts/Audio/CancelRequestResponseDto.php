<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class CancelRequestResponseDto
{
    #[Serialize]
    private CancelRequestStatus $status = CancelRequestStatus::Cancelled;

    #[Serialize]
    private bool $tooLate = false;

    public static function getInstance(CancelRequestStatus $status, bool $tooLate): self
    {
        return (new self())
            ->setStatus($status)
            ->setTooLate($tooLate)
        ;
    }

    public function getStatus(): CancelRequestStatus
    {
        return $this->status;
    }

    public function setStatus(CancelRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isTooLate(): bool
    {
        return $this->tooLate;
    }

    public function setTooLate(bool $tooLate): self
    {
        $this->tooLate = $tooLate;

        return $this;
    }
}
