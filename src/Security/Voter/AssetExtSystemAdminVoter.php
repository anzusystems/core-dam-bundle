<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Only admins to ExtSystem subject can access all assets under the system.
 */
final class AssetExtSystemAdminVoter extends AbstractVoter
{
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if (false === ($subject instanceof ExtSystem)) {
            return false;
        }

        return $user->getAdminToExtSystems()->containsKey($subject->getId());
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_VIEW,
        ];
    }
}
