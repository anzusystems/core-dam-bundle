<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageSyncDto;

final readonly class UsageClaim
{
    public function __construct(
        private string $scopeName,
        private string $scopeId,
        private string $holderName,
        private string $holderId,
    ) {
    }

    public static function fromDto(ImageUsageSyncDto $dto): self
    {
        return new self(
            $dto->getScopeResourceName(),
            $dto->getScopeResourceId(),
            $dto->getHolderResourceName(),
            $dto->getHolderResourceId(),
        );
    }

    public static function released(): self
    {
        return new self(App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING);
    }

    public function getScopeName(): string
    {
        return $this->scopeName;
    }

    public function getScopeId(): string
    {
        return $this->scopeId;
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
        return $attributes->getUsedByScopeName() === $this->scopeName
            && $attributes->getUsedByScopeId() === $this->scopeId
            && $attributes->getUsedByResourceName() === $this->holderName
            && $attributes->getUsedByResourceId() === $this->holderId;
    }
}
