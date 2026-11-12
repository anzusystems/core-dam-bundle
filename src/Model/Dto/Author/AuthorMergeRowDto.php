<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

/**
 * An author as the merge CSV saw it; the merge proceeds only while the author still looks the same.
 */
final readonly class AuthorMergeRowDto
{
    public function __construct(
        public string $authorId,
        public string $name,
        public string $identifier,
    ) {
    }
}
