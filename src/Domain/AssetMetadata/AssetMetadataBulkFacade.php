<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetMetadataBulkManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetChangedEventDispatcher;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Traits\IndexManagerAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AssetMetadataBulkFacade
{
    use ValidatorAwareTrait;
    use IndexManagerAwareTrait;

    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
        private readonly AssetManager $assetManager,
        private readonly AccessDenier $accessDenier,
        private readonly AssetMetadataBulkManager $assetMetadataBulkManager,
        private readonly AssetChangedEventDispatcher $assetMetadataBulkEventDispatcher,
    ) {
    }

    /**
     * @param Collection<int, FormProvidableMetadataBulkUpdateDto> $list
     *
     * @throws ValidationException
     * @throws AccessDeniedException
     * @throws NonUniqueResultException
     */
    public function bulkUpdate(Collection $list): Collection
    {
        $this->validateMaxBulkCount($list);
        $this->validator->validate($list);
        $updated = [];
        /** @var Asset[] $affectedAssets */
        $affectedAssets = [];

        foreach ($list as $updateDto) {
            $this->checkPermissions($updateDto);
            $asset = $updateDto->getAsset();

            if ($this->isAssetChanged($asset, $updateDto)) {
                $affectedAssets[] = $asset;
            }

            $updated[] = FormProvidableMetadataBulkUpdateDto::getInstance(
                asset: $this->assetMetadataBulkManager->updateFromMetadataBulkDto($asset, $updateDto, false)
            );
        }

        $this->assetManager->flush();

        if (false === empty($affectedAssets)) {
            $this->assetMetadataBulkEventDispatcher->dispatchAssetChangedEvent(
                new ArrayCollection($affectedAssets),
            );
        }

        foreach ($list as $updateDto) {
            $this->indexManager->index($updateDto->getAsset());
        }

        return new ArrayCollection($updated);
    }

    private function isAssetChanged(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): bool
    {
        return ($updateDto->isCustomDataUndefined() && false === empty($asset->getMetadata()->getCustomData())) ||
            (false === $updateDto->isCustomDataUndefined() && false === ($asset->getMetadata()->getCustomData() === $updateDto->getCustomData())) ||
            ($updateDto->isAuthorsUndefined() && false === $asset->getAuthors()->isEmpty()) ||
            (false === $updateDto->isAuthorsUndefined() && false === CollectionHelper::colDiff($asset->getAuthors(), $updateDto->getAuthors())->isEmpty());
    }

    /**
     * @param Collection<int, FormProvidableMetadataBulkUpdateDto> $dtoList
     */
    private function validateMaxBulkCount(Collection $dtoList): void
    {
        if ($dtoList->count() > $this->configurationProvider->getSettings()->getMaxBulkItemCount()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_BULK_SIZE_EXCEEDED);
        }
    }

    private function checkPermissions(FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $updateDto->getAsset());
        if (false === $updateDto->isAuthorsUndefined()) {
            foreach ($updateDto->getAuthors() as $author) {
                $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_AUTHOR_READ, $author);
            }
        }

        if (false === $updateDto->isKeywordsUndefined()) {
            foreach ($updateDto->getKeywords() as $keyword) {
                $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_KEYWORD_READ, $keyword);
            }
        }
    }
}
