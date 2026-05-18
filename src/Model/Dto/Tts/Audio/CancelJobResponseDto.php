<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class CancelJobResponseDto
{
    #[Serialize]
    private CancelJobStatus $status = CancelJobStatus::Cancelled;

    #[Serialize]
    private bool $tooLate = false;

    public static function getInstance(CancelJobStatus $status, bool $tooLate): self
    {
        return (new self())
            ->setStatus($status)
            ->setTooLate($tooLate)
        ;
    }

    public function getStatus(): CancelJobStatus
    {
        return $this->status;
    }

    public function setStatus(CancelJobStatus $status): self
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
