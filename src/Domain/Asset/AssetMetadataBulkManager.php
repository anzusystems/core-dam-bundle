<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;

class AssetMetadataBulkManager extends AbstractManager
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly AssetMetadataManager $assetMetadataManager,
        private readonly AuthorProvider $authorProvider,
    ) {
    }

    public function updateFromMetadataBulkDto(
        Asset $asset,
        FormProvidableMetadataBulkUpdateDto $dto,
        bool $flush = true
    ): Asset {
        $this->updateMetadata($asset, $dto);
        $this->updateDescribed($asset, $dto);
        $this->updateMainFileSingleUse($asset, $dto);
        $this->updateAuthors($asset, $dto);
        $this->updateKeywords($asset, $dto);

        return $this->assetManager->updateExisting($asset, $flush);
    }

    private function updateKeywords(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        if ($updateDto->isKeywordsUndefined()) {
            return;
        }

        $this->colUpdate(
            oldCollection: $asset->getKeywords(),
            newCollection: $updateDto->getKeywords(),
        );
    }

    private function updateAuthors(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        if ($updateDto->isAuthorsUndefined()) {
            return;
        }

        $this->colUpdate(
            oldCollection: $asset->getAuthors(),
            newCollection: $updateDto->getAuthors(),
        );
        $this->authorProvider->provideCurrentAuthorToColl($asset);
    }

    private function updateMainFileSingleUse(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        if ($updateDto->isMainFileSingleUndefined()) {
            return;
        }

        $mainFile = $asset->getMainFile();
        if ($mainFile instanceof AssetFile) {
            $mainFile->getFlags()->setSingleUse($updateDto->isMainFileSingleUse());
        }
    }

    private function updateMetadata(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        if ($updateDto->isCustomDataUndefined()) {
            return;
        }

        $this->assetMetadataManager->updateFromCustomData($asset, $updateDto->getCustomData(), false);
    }

    private function updateDescribed(Asset $asset, FormProvidableMetadataBulkUpdateDto $updateDto): void
    {
        if ($updateDto->isDescribedUndefined()) {
            return;
        }

        $asset->getAssetFlags()
            ->setDescribed($updateDto->isDescribed());

        if ($updateDto->isDescribed()) {
            $this->assetMetadataManager->removeSuggestions($updateDto->getAsset()->getMetadata(), false);
        }
    }
}
