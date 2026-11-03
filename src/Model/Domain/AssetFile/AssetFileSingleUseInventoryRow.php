<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\AssetFile;

use DateTimeImmutable;

final readonly class AssetFileSingleUseInventoryRow
{
    /**
     * @param list<string> $holders
     */
    public function __construct(
        private string $rootId,
        private string $assetId,
        private string $licenceId,
        private string $recordedHolder,
        private array $holders,
        private ?DateTimeImmutable $firstUsedAt,
    ) {
    }

    public function getRootId(): string
    {
        return $this->rootId;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getLicenceId(): string
    {
        return $this->licenceId;
    }

    public function getRecordedHolder(): string
    {
        return $this->recordedHolder;
    }

    /**
     * @return list<string>
     */
    public function getHolders(): array
    {
        return $this->holders;
    }

    public function getFirstUsedAt(): ?DateTimeImmutable
    {
        return $this->firstUsedAt;
    }
}
