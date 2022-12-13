<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsProcessor;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Security\AccessDenier;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Validator\EntityValidator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AssetMetadataBulkFacade
{
    public function __construct(
        private readonly EntityValidator $entityValidator,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly AssetManager $assetManager,
        private readonly IndexManager $indexManager,
        private readonly AccessDenier $accessDenier,
        private readonly AssetTextsProcessor $displayTitleProcessor,
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
        $this->entityValidator->validateDto($list);
        $updated = [];

        foreach ($list as $updateDto) {
            $this->checkPermissions($updateDto);
            $asset = $updateDto->getAsset();

            $this->assetMetadataManager->updateFromMetadataBulkDto($asset->getMetadata(), $updateDto, false);
            $updated[] =
                FormProvidableMetadataBulkUpdateDto::getInstance(
                    $this->assetManager->updateFromMetadataBulkDto($asset, $updateDto, false)
                );
            if ($updateDto->isDescribed()) {
                $this->assetMetadataManager->removeSuggestions($updateDto->getAsset()->getMetadata(), false);
            }
            $this->displayTitleProcessor->updateAssetDisplayTitle($asset);
        }

        $this->assetManager->flush();
        foreach ($list as $updateDto) {
            $this->indexManager->index($updateDto->getAsset());
        }

        return new ArrayCollection($updated);
    }

    private function validateMaxBulkCount(Collection $dtoList): void
    {
        if ($dtoList->count() > $this->configurationProvider->getSettings()->getMaxBulkItemCount()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::DETAIL_BULK_SIZE_EXCEEDED);
        }
    }

    private function checkPermissions(FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $updateDto->getAsset());
        foreach ($updateDto->getAuthors() as $author) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_AUTHOR_VIEW, $author);
        }
        foreach ($updateDto->getKeywords() as $keyword) {
            $this->accessDenier->denyUnlessGranted(DamPermissions::DAM_KEYWORD_VIEW, $keyword);
        }
    }
}
