<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorMergeGroupDto
{
    /**
     * @param non-empty-list<AuthorMergeRowDto> $variants
     */
    public function __construct(
        public string $name,
        public AuthorMergeRowDto $main,
        public array $variants,
    ) {
    }
}
