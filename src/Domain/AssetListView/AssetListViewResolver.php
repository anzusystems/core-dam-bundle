<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetListView;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetListView\AssetListViewScope;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;
use AnzuSystems\CoreDamBundle\Security\Voter\LicenceVoterTrait;

final readonly class AssetListViewResolver
{
    use LicenceVoterTrait;

    public function __construct(
        private AssetListViewRepository $assetListViewRepository,
    ) {
    }

    /**
     * Views applying to the user, targeted ones first, each narrowed down to the licences he may read.
     *
     * @return list<AssetListViewScope>
     */
    public function resolveForUser(DamUser $user): array
    {
        // Warm up EXTRA_LAZY collections up front - initialized containsKey() stays in memory,
        // an uninitialized one hits the DB on every call inside the loop below.
        $user->getAdminToExtSystems()->toArray();
        $user->getUserToExtSystems()->toArray();
        $user->getAssetLicences()->toArray();
        foreach ($user->getLicenceGroups() as $licenceGroup) {
            $licenceGroup->getLicences()->toArray();
        }

        $views = [
            ...$this->assetListViewRepository->findByGroups($user->getLicenceGroups()->getKeys()),
            ...$this->assetListViewRepository->findWithoutGroups(),
        ];

        $scopes = [];
        foreach ($views as $view) {
            $licenceIds = $this->getGrantedLicenceIds($view, $user);
            if ([] === $licenceIds) {
                continue;
            }

            $scopes[] = new AssetListViewScope($view, $licenceIds);
        }

        return $scopes;
    }

    /**
     * @return list<int>
     */
    private function getGrantedLicenceIds(AssetListView $view, DamUser $user): array
    {
        $superAdmin = in_array(AnzuUser::ROLE_SUPER_ADMIN, $user->getRoles(), true);

        $licenceIds = [];
        foreach ($view->getLicences() as $licence) {
            $sameExtSystem = $licence->getExtSystem()->getId() === $view->getExtSystem()->getId();
            if ($sameExtSystem && ($superAdmin || $this->licencePermissionGranted($licence, $user))) {
                $licenceIds[] = (int) $licence->getId();
            }
        }

        return $licenceIds;
    }
}
