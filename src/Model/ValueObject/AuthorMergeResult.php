<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

use AnzuSystems\CoreDamBundle\App;

final class AuthorMergeResult
{
    private int $mergedGroups = App::ZERO;
    private int $mergedVariants = App::ZERO;
    private int $alreadyMergedGroups = App::ZERO;
    private int $assetLinks = App::ZERO;
    private int $cleanPhrases = App::ZERO;
    private int $jobs = App::ZERO;

    /**
     * @var array<string, string>
     */
    private array $skippedGroups = [];

    public function __construct(
        private readonly string $runId,
    ) {
    }

    public function addMerged(int $variants, int $assetLinks, int $cleanPhrases, int $jobs): void
    {
        ++$this->mergedGroups;
        $this->mergedVariants += $variants;
        $this->assetLinks += $assetLinks;
        $this->cleanPhrases += $cleanPhrases;
        $this->jobs += $jobs;
    }

    public function addAlreadyMerged(): void
    {
        ++$this->alreadyMergedGroups;
    }

    public function addSkipped(string $groupName, string $reason): void
    {
        $this->skippedGroups[$groupName] = $reason;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getMergedGroups(): int
    {
        return $this->mergedGroups;
    }

    public function getMergedVariants(): int
    {
        return $this->mergedVariants;
    }

    public function getAlreadyMergedGroups(): int
    {
        return $this->alreadyMergedGroups;
    }

    public function getAssetLinks(): int
    {
        return $this->assetLinks;
    }

    public function getCleanPhrases(): int
    {
        return $this->cleanPhrases;
    }

    public function getJobs(): int
    {
        return $this->jobs;
    }

    /**
     * @return array<string, string> reason by group
     */
    public function getSkippedGroups(): array
    {
        return $this->skippedGroups;
    }
}
