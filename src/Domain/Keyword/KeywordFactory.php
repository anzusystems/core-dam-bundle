<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Keyword;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Keyword;

final class KeywordFactory
{
    public function create(string $name, ExtSystem $extSystem): Keyword
    {
        return (new Keyword())
            ->setName($name)
            ->setExtSystem($extSystem)
        ;
    }
}
