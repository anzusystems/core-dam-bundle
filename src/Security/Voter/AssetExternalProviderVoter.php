<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

final class AssetExternalProviderVoter extends AbstractVoter
{
    /**
     * @param DamUser $user
     */
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if (false === is_string($subject)) {
            return false;
        }

        return $user->hasAllowedExternalProvider($subject);
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS,
        ];
    }
}
