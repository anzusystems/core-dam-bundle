<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

/**
 * One proposed merge group: the CSV rows of its members, values by column.
 */
final readonly class AuthorMergeProposalDto
{
    /**
     * @param list<array<string, string|int>> $rows
     */
    public function __construct(
        public bool $manual,
        public string $variant,
        public int $assetCount,
        public string $key,
        public array $rows,
    ) {
    }
}
