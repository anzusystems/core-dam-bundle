<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorMergeCandidateDto
{
    /**
     * @param list<string> $currentAuthorIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $nameNormalized,
        public string $identifier,
        public bool $reviewed,
        public int $assetCount,
        public string $createdAt,
        public string $createdBy,
        public array $currentAuthorIds,
        public int $childAuthorCount,
    ) {
    }
}
