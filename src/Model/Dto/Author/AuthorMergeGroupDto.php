<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorMergeGroupDto
{
    /**
     * @param list<AuthorMergeRowDto> $variants empty when only the main author goes under its targets
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
