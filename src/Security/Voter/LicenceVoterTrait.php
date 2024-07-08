<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\DamUser;

trait LicenceVoterTrait
{
    protected function licencePermissionGranted(AssetLicence $licence, DamUser $user): bool
    {
        return $user->getAdminToExtSystems()->containsKey((int) $licence->getExtSystem()->getId())
            || $user->getUserToExtSystems()->containsKey((int) $licence->getExtSystem()->getId())
            || $user->getAssetLicences()->containsKey((int) $licence->getId())
            || $this->licenceGroupGrants($licence, $user)
        ;
    }

    private function licenceGroupGrants(AssetLicence $licence, DamUser $user): bool
    {
        foreach ($user->getLicenceGroups() as $licenceGroup) {
            if ($licenceGroup->getLicences()->containsKey((int) $licence->getId())) {
                return true;
            }
        }

        return false;
    }
}
