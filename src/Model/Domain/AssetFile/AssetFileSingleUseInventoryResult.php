<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

final readonly class AssetFileSingleUseInventoryResult
{
    public function __construct(
        private int $checked,
        private int $withManyHolders,
        private int $unanswered,
    ) {
    }

    public function getChecked(): int
    {
        return $this->checked;
    }

    public function getWithManyHolders(): int
    {
        return $this->withManyHolders;
    }

    public function getUnanswered(): int
    {
        return $this->unanswered;
    }
}
