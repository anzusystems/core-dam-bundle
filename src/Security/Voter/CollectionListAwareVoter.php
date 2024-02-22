<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\LicenceCollectionInterface;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Subject for these ACLs must be defined otherwise access will be revoked. Subject must implement ExtSystemInterface.
 *
 * @template-extends AbstractVoter<string, ExtSystemInterface>
 */
final class CollectionListAwareVoter extends AbstractVoter
{
    /**
     * @param ExtSystemInterface $subject
     * @param DamUser $user
     */
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }
        if (false === ($subject instanceof LicenceCollectionInterface)) {
            return false;
        }

        // todo performance improvement
        foreach ($subject->getLicences() as $licence) {
            if (
                false === $user->getAssetLicences()->containsKey((int) $licence->getId()) &&
                false === $user->getAdminToExtSystems()->containsKey((int) $licence->getExtSystem()->getId()) &&
                false === $user->getUserToExtSystems()->containsKey((int) $licence->getExtSystem()->getId())
            ) {
                return false;
            }
        }

        return true;
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_VIEW,
            DamPermissions::DAM_DISTRIBUTION_VIEW,
        ];
    }
}
