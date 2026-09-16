<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;

final readonly class UsageClaim
{
    public function __construct(
        private string $holderName,
        private string $holderId,
    ) {
    }

    public static function released(): self
    {
        return new self(App::EMPTY_STRING, App::EMPTY_STRING);
    }

    public function getHolderName(): string
    {
        return $this->holderName;
    }

    public function getHolderId(): string
    {
        return $this->holderId;
    }

    public function matches(AssetFileAttributes $attributes): bool
    {
        return $attributes->getUsedByHolderName() === $this->holderName
            && $attributes->getUsedByHolderId() === $this->holderId;
    }
}
