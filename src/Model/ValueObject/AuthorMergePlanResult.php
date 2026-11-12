<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\ValueObject;

final readonly class AuthorMergePlanResult
{
    public function __construct(
        public int $groups,
        public int $manualGroups,
        public int $authors,
        public int $variants,
    ) {
    }
}
