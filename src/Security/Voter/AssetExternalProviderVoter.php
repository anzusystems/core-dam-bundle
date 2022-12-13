<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

final class AssetExternalProviderVoter extends AbstractVoter
{
    protected function resolveAllow(string $attribute, mixed $subject, DamUser $user): bool
    {
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
