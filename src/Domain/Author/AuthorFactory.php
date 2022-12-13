<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Author;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

final class AuthorFactory
{
    public function create(string $name, ExtSystem $extSystem): Author
    {
        return (new Author())
            ->setName($name)
            ->setExtSystem($extSystem)
        ;
    }
}
