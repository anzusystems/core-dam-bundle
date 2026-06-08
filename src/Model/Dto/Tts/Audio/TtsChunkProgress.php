<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/** Read projection of chunk row counts; absent on single-run requests. */
final class TtsChunkProgress
{
    #[Serialize]
    private int $total = 0;

    #[Serialize]
    private int $done = 0;

    #[Serialize]
    private int $failed = 0;

    public static function fromCounts(int $total, int $done, int $failed): self
    {
        return (new self())->setTotal($total)->setDone($done)->setFailed($failed);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getDone(): int
    {
        return $this->done;
    }

    public function setDone(int $done): self
    {
        $this->done = $done;

        return $this;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function setFailed(int $failed): self
    {
        $this->failed = $failed;

        return $this;
    }
}
