<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Subject for these ACLs must be defined otherwise access will be revoked. Subject must implement ExtSystemInterface.
 */
final class ExtSystemAwareVoter extends AbstractVoter
{
    protected function resolveAllow(string $attribute, mixed $subject, DamUser $user): bool
    {
        if (null === $subject) {
            return true;
        }

        if (false === ($subject instanceof ExtSystemInterface)) {
            return false;
        }

        $extSystemId = $subject->getExtSystem()->getId();

        if ($user->getAdminToExtSystems()->containsKey($extSystemId)) {
            return true;
        }

        return $user->getUserToExtSystems()->containsKey($extSystemId);
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_AUTHOR_VIEW,
            DamPermissions::DAM_AUTHOR_CREATE,
            DamPermissions::DAM_AUTHOR_UPDATE,
            DamPermissions::DAM_AUTHOR_DELETE,
            DamPermissions::DAM_KEYWORD_VIEW,
            DamPermissions::DAM_KEYWORD_CREATE,
            DamPermissions::DAM_KEYWORD_UPDATE,
            DamPermissions::DAM_KEYWORD_DELETE,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_CREATE,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_UPDATE,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_VIEW,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_DELETE,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE,
            DamPermissions::DAM_DISTRIBUTION_CATEGORY_SELECT_VIEW,
            DamPermissions::DAM_EXT_SYSTEM_VIEW,
            DamPermissions::DAM_CUSTOM_FORM_UPDATE,
            DamPermissions::DAM_CUSTOM_FORM_VIEW,
            DamPermissions::DAM_CUSTOM_FORM_CREATE,
            DamPermissions::DAM_PODCAST_VIEW,
            DamPermissions::DAM_PODCAST_DELETE,
            DamPermissions::DAM_PODCAST_UPDATE,
            DamPermissions::DAM_PODCAST_CREATE,
            DamPermissions::DAM_PODCAST_EPISODE_VIEW,
            DamPermissions::DAM_PODCAST_EPISODE_DELETE,
            DamPermissions::DAM_PODCAST_EPISODE_UPDATE,
            DamPermissions::DAM_PODCAST_EPISODE_CREATE,
        ];
    }
}
