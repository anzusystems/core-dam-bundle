<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Author;

final readonly class AuthorNameDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $nameNormalized,
    ) {
    }
}
