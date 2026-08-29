<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;
use Throwable;

final class AssetLicenceGroupFacade
{
    use ValidatorAwareTrait;

    public const string ERROR_LICENCE_REQUIRED_BY_LIST_VIEW = 'error_licence_required_by_list_view';

    public function __construct(
        private readonly AssetLicenceGroupManager $assetLicenceGroupManager,
        private readonly AssetListViewRepository $assetListViewRepository,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function create(AssetLicenceGroup $assetLicenceGroup): AssetLicenceGroup
    {
        $this->validator->validate($assetLicenceGroup);

        return $this->assetLicenceGroupManager->create($assetLicenceGroup);
    }

    /**
     * @throws ValidationException
     */
    public function update(AssetLicenceGroup $assetLicenceGroup, AssetLicenceGroup $newAssetLicenceGroup): AssetLicenceGroup
    {
        $this->validator->validate($newAssetLicenceGroup, $assetLicenceGroup);

        $removedLicenceIds = array_values(CollectionHelper::traversableToIds(
            CollectionHelper::colDiff($assetLicenceGroup->getLicences(), $newAssetLicenceGroup->getLicences())
        ));

        $this->assetLicenceGroupManager->beginTransaction();

        try {
            $this->assetLicenceGroupManager->update($assetLicenceGroup, $newAssetLicenceGroup, flush: false);
            $this->assetListViewRepository->removeLicencesUnreachableByOtherGroups($removedLicenceIds, $assetLicenceGroup);
            if ($this->assetListViewRepository->countWithoutLicences() > App::ZERO) {
                throw (new ValidationException())->addFormattedError('licences', self::ERROR_LICENCE_REQUIRED_BY_LIST_VIEW);
            }
            $this->assetLicenceGroupManager->flush();
            $this->assetLicenceGroupManager->commit();
        } catch (ValidationException $exception) {
            $this->assetLicenceGroupManager->rollback();

            throw $exception;
        } catch (Throwable $exception) {
            if ($this->assetLicenceGroupManager->isTransactionActive()) {
                $this->assetLicenceGroupManager->rollback();
            }

            throw new RuntimeException('asset_licence_group_update_failed', 0, $exception);
        }

        return $assetLicenceGroup;
    }
}
