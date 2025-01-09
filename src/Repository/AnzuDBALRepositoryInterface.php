<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

interface AnzuDBALRepositoryInterface
{
    public function getTableName(): string;
}
