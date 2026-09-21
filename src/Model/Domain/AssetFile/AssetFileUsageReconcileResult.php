<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

final readonly class AssetFileUsageReconcileResult
{
    public function __construct(
        private int $checked,
        private int $confirmed,
        private int $released,
        private int $skipped,
        private int $unanswered,
    ) {
    }

    public function getChecked(): int
    {
        return $this->checked;
    }

    public function getConfirmed(): int
    {
        return $this->confirmed;
    }

    public function getReleased(): int
    {
        return $this->released;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getUnanswered(): int
    {
        return $this->unanswered;
    }
}
