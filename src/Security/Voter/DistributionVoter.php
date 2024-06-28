<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

/**
 * @template-extends AbstractVoter<string, Distribution|string>
 */
final class DistributionVoter extends AbstractVoter
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetLicenceAwareVoter $assetLicenceAwareVoter,
    ) {
    }

    /**
     * @param Distribution|string $subject
     * @param DamUser $user
     */
    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if (is_string($subject)) {
            return $user->hasAllowedDistributionServices($subject);
        }

        if (false === ($subject instanceof Distribution)) {
            return false;
        }

        $asset = $this->assetRepository->find($subject->getAssetId());
        if (null === $asset) {
            return false;
        }

        $assetFile = $this->assetFileRepository->find($subject->getAssetFileId());
        if (null === $assetFile) {
            return false;
        }

        return $this->assetLicenceAwareVoter->permissionVote(DamPermissions::DAM_ASSET_READ, $asset, $user)
            && $this->assetLicenceAwareVoter->permissionVote(DamPermissions::DAM_ASSET_READ, $assetFile, $user);
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_DISTRIBUTION_ACCESS,
        ];
    }
}
