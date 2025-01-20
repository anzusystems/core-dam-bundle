<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\EntityIterator\Model;

use AnzuSystems\CoreDamBundle\Domain\EntityIterator\Visitor\EntityIteratorOnBatchVisitorInterface;

final readonly class EntityIteratorConfig
{
    /**
     * @param class-string<EntityIteratorOnBatchVisitorInterface>|null $useOnBatchVisitor
     */
    public function __construct(
        private ?string $fromId = null,
        private ?string $toId = null,
        private int $batch = 500,
        private ?int $limit = null,
        private bool $fetchTotalCount = true,
        private ?string $useOnBatchVisitor = null,
    ) {
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getFromId(): ?string
    {
        return $this->fromId;
    }

    public function getToId(): ?string
    {
        return $this->toId;
    }

    public function getBatch(): int
    {
        return $this->batch;
    }

    /**
     * @return class-string<EntityIteratorOnBatchVisitorInterface>|null
     */
    public function getUseOnBatchVisitor(): ?string
    {
        return $this->useOnBatchVisitor;
    }

    public function isFetchTotalCount(): bool
    {
        return $this->fetchTotalCount;
    }
}
