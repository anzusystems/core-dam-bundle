<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\CoreDamBundle\Entity\PermissionGroup;

/**
 * @extends AbstractAnzuRepository<PermissionGroup>
 *
 * @method PermissionGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method PermissionGroup|null findOneBy(array $criteria, array $orderBy = null)
 */
final class PermissionGroupRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return PermissionGroup::class;
    }
}
