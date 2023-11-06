<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * Subject doesn't matter, all it's required is ACL itself.
 *
 * @template-extends AbstractVoter<string, mixed>
 */
final class BaseVoter extends AbstractVoter
{
    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_ASSET_LICENCE_CREATE,
            DamPermissions::DAM_ASSET_LICENCE_UPDATE,
            DamPermissions::DAM_ASSET_LICENCE_LIST,
            DamPermissions::DAM_EXT_SYSTEM_LIST,
            DamPermissions::DAM_EXT_SYSTEM_UPDATE,
            DamPermissions::DAM_CUSTOM_FORM_ELEMENT_VIEW,
            DamPermissions::DAM_JOB_VIEW,
            DamPermissions::DAM_JOB_CREATE,
            DamPermissions::DAM_JOB_DELETE,
        ];
    }
}
