<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository\Interfaces;

use AnzuSystems\CommonBundle\Repository\AnzuRepositoryInterface as BaseAnzuRepositoryInterface;
use Doctrine\Common\Collections\Collection;

interface AnzuRepositoryInterface extends BaseAnzuRepositoryInterface
{
    public function getAll(string $idFrom = '', string $idUntil = '', int $limit = 500): Collection;

    public function getAllCount(string $idFrom = '', string $idUntil = ''): int;
}
