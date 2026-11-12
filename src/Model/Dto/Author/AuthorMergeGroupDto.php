<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorMergeGroupDto
{
    /**
     * @param non-empty-list<AuthorMergeRowDto> $variants
     * @param non-empty-list<string> $targetIds the authors the variants go under, as the plan approved them
     */
    public function __construct(
        public string $name,
        public AuthorMergeRowDto $main,
        public array $variants,
        public array $targetIds,
    ) {
    }
}
