<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Subject for these ACLs must be defined otherwise access will be revoked. Subject must implement AssetLicenceInterface.
 *
 * @template-extends AbstractVoter<string, AssetLicenceInterface>
 */
final class AssetLicenceAwareVoter extends AbstractVoter
{
    use LicenceVoterTrait;
    /**
     * @param AssetLicenceInterface $subject
     * @param DamUser $user
     */
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if (false === ($subject instanceof AssetLicenceInterface)) {
            return false;
        }

        $assetLicence = $subject->getLicence();

        return $this->licencePermissionGranted($assetLicence, $user);
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
