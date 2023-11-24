<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Only admins to ExtSystem subject can access all assets under the system.
 *
 * @template-extends AbstractVoter<string, ExtSystem>
 */
final class AssetExtSystemAdminVoter extends AbstractVoter
{
    /**
     * @param ExtSystem $subject
     * @param DamUser $user
     */
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if (false === ($subject instanceof ExtSystem)) {
            return false;
        }

        return $user->getAdminToExtSystems()->containsKey((int) $subject->getId());
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_VIEW,
        ];
    }
}
