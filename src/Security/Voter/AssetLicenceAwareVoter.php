<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Subject for these ACLs must be defined otherwise access will be revoked. Subject must implement AssetLicenceInterface.
 */
final class AssetLicenceAwareVoter extends AbstractVoter
{
    public function resolveAllow(string $attribute, mixed $subject, DamUser $user): bool
    {
        if (false === ($subject instanceof AssetLicenceInterface)) {
            return false;
        }

        $assetLicence = $subject->getLicence();

        if ($user->getAssetLicences()->containsKey($assetLicence->getId())) {
            return true;
        }

        return $user->getAdminToExtSystems()->containsKey($assetLicence->getExtSystem()->getId());
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_CREATE,
            DamPermissions::DAM_ASSET_UPDATE,
            DamPermissions::DAM_ASSET_VIEW,
            DamPermissions::DAM_ASSET_DELETE,
            DamPermissions::DAM_VIDEO_CREATE,
            DamPermissions::DAM_VIDEO_UPDATE,
            DamPermissions::DAM_VIDEO_VIEW,
            DamPermissions::DAM_VIDEO_DELETE,
            DamPermissions::DAM_AUDIO_CREATE,
            DamPermissions::DAM_AUDIO_UPDATE,
            DamPermissions::DAM_AUDIO_VIEW,
            DamPermissions::DAM_AUDIO_DELETE,
            DamPermissions::DAM_DOCUMENT_CREATE,
            DamPermissions::DAM_DOCUMENT_UPDATE,
            DamPermissions::DAM_DOCUMENT_VIEW,
            DamPermissions::DAM_IMAGE_CREATE,
            DamPermissions::DAM_IMAGE_UPDATE,
            DamPermissions::DAM_IMAGE_VIEW,
            DamPermissions::DAM_IMAGE_DELETE,
            DamPermissions::DAM_REGION_OF_INTEREST_CREATE,
            DamPermissions::DAM_REGION_OF_INTEREST_VIEW,
            DamPermissions::DAM_REGION_OF_INTEREST_UPDATE,
            DamPermissions::DAM_REGION_OF_INTEREST_DELETE,
            DamPermissions::DAM_ASSET_LICENCE_VIEW,
        ];
    }
}
