<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use Symfony\Component\Console\Input\InputInterface;

final class RebuildIndexConfig
{
    public const string ARG_INDEX_NAME = 'index-name';
    public const string OPT_EXT_SYSTEM = 'ext-system';
    public const string OPT_ID_FROM = 'id-from';
    public const string OPT_ID_UNTIL = 'id-until';
    public const string OPT_NO_DROP = 'no-drop';
    public const string OPT_BATCH = 'batch';

    private ?string $lastProcessedId = null;
    private ?string $maxId = null;

    public function __construct(
        private readonly string $indexName,
        private readonly string $extSystemSlug,
        private readonly string $idFrom,
        private readonly string $idUntil,
        private readonly bool $noDrop,
        private readonly int $batchSize,
    ) {
    }

    public static function createFromInput(InputInterface $input): self
    {
        return new self(
            indexName: (string) $input->getArgument(self::ARG_INDEX_NAME),
            extSystemSlug: (string) $input->getOption(self::OPT_EXT_SYSTEM),
            idFrom: (string) $input->getOption(self::OPT_ID_FROM),
            idUntil: (string) $input->getOption(self::OPT_ID_UNTIL),
            noDrop: (bool) $input->getOption(self::OPT_NO_DROP),
            batchSize: (int) $input->getOption(self::OPT_BATCH),
        );
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getExtSystemSlug(): string
    {
        return $this->extSystemSlug;
    }

    public function hasExtSystemSlug(): bool
    {
        return false === empty($this->getExtSystemSlug());
    }

    public function getIdFrom(): string
    {
        return $this->idFrom;
    }

    public function hasIdFrom(): bool
    {
        return false === empty($this->getIdFrom());
    }

    public function getIdUntil(): string
    {
        return $this->idUntil;
    }

    public function hasNotIdUntil(): bool
    {
        return empty($this->getIdUntil());
    }

    public function hasIdUntil(): bool
    {
        return false === $this->hasNotIdUntil();
    }

    public function isNoDrop(): bool
    {
        return $this->noDrop;
    }

    public function isDrop(): bool
    {
        return false === $this->isNoDrop();
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function setLastProcessedId(string $lastProcessedId): self
    {
        $this->lastProcessedId = $lastProcessedId;

        return $this;
    }

    public function getLastProcessedId(): ?string
    {
        return $this->lastProcessedId;
    }

    public function hasLastProcessedId(): bool
    {
        return is_string($this->getLastProcessedId());
    }

    public function setMaxId(string $maxId): self
    {
        $this->maxId = $maxId;

        return $this;
    }

    public function getMaxId(): ?string
    {
        return $this->maxId;
    }

    public function hasMaxId(): bool
    {
        return is_string($this->getMaxId());
    }

    public function getResolvedMaxId(): string
    {
        return (string) ($this->hasMaxId() ? $this->getMaxId() : $this->getIdUntil());
    }
}
