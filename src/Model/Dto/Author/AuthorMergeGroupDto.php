<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorMergeGroupDto
{
    /**
     * @param list<AuthorMergeRowDto> $variants empty when only the main author goes under its targets
     * @param list<string> $targetIds the authors the variants go under, as the plan approved them
     * @param list<string> $newSourceNames targets named only, found or created as sources
     * @param list<string> $newAuthorNames targets named only, found or created as authors
     */
    public function __construct(
        public string $name,
        public AuthorMergeRowDto $main,
        public array $variants,
        public array $targetIds,
        public array $newSourceNames,
        public array $newAuthorNames,
    ) {
    }
}
