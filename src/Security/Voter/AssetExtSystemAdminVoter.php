<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Only admins to ExtSystem subject can access all assets under the system.
 */
final class AssetExtSystemAdminVoter extends AbstractVoter
{
    protected function resolveAllow(string $attribute, mixed $subject, DamUser $user): bool
    {
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
