<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Security\Permission\Grants;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractVoter extends Voter
{
    protected Security $security;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, $this->getSupportedPermissions(), true);
    }

    /**
     * @throws AnzuException
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var DamUser $user */
        $user = $token->getUser();

        // If role admin, grant access
        if ($this->security->isGranted(AnzuUser::ROLE_ADMIN)) {
            return true;
        }

        $userPermissions = $user->getResolvedPermissions();
        $userPermissionGrant = $userPermissions[$attribute];

        return match ($userPermissionGrant) {
            Grants::GRANT_DENY => false,
            Grants::GRANT_ALLOW => $this->resolveAllow($attribute, $subject, $user),
            Grants::GRANT_ALLOW_OWNER => $this->resolveAllowOwner($attribute, $subject, $user),
            default => throw new AnzuException('User permission could not be resolved!'),
        };
    }

    protected function resolveAllow(string $attribute, mixed $subject, DamUser $user): bool
    {
        return true;
    }

    protected function resolveAllowOwner(string $attribute, mixed $subject, DamUser $user): bool
    {
        throw new RuntimeException(sprintf('Please create voter for %s and %s!', $attribute, $subject::class));
    }

    abstract protected function getSupportedPermissions(): array;
}
