<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Security\Voter;

use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;

final class DistributionVoter extends AbstractVoter
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly AssetFileRepository $assetFileRepository,
        private readonly AssetLicenceAwareVoter $assetLicenceAwareVoter,
    ) {
    }

    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        if (false === parent::permissionVote($attribute, $subject, $user)) {
            return false;
        }

        if ($subject instanceof Distribution) {
            $asset = $this->assetRepository->find($subject->getAssetId());
            $assetFile = $this->assetFileRepository->find($subject->getAssetFileId());

            return $this->assetLicenceAwareVoter->resolveAllow(DamPermissions::DAM_ASSET_VIEW, $asset, $user)
                && $this->assetLicenceAwareVoter->resolveAllow(DamPermissions::DAM_ASSET_VIEW, $assetFile, $user);
        }

        if (false === is_string($subject)) {
            return false;
        }

        return $user->hasAllowedDistributionServices($subject);
    }

    protected function getSupportedPermissions(): array
    {
        return [
            DamPermissions::DAM_DISTRIBUTION_ACCESS,
        ];
    }
}
