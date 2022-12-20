<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;

class AssetManager extends AbstractManager
{
    public function __construct(
        private readonly AssetPropertiesRefresher $propertiesRefresher
    ) {
    }

    public function create(Asset $asset, bool $flush = true): Asset
    {
        $this->trackCreation($asset);
        $this->entityManager->persist($asset);
        $this->flush($flush);

        return $asset;
    }

    public function updateExisting(Asset $asset, bool $flush = true): Asset
    {
        $this->trackModification($asset);
        $this->propertiesRefresher->refreshProperties($asset);
        $this->flush($flush);

        return $asset;
    }

    public function delete(Asset $asset, bool $flush = true): bool
    {
        $asset->setMainFile(null);
        $this->entityManager->remove($asset);
        $this->flush($flush);

        return true;
    }

    public function updateFromMetadataBulkDto(
        Asset $asset,
        FormProvidableMetadataBulkUpdateDto $dto,
        bool $flush = true
    ): Asset {
        $asset->getAssetFlags()
            ->setDescribed($dto->isDescribed());
        $this->colUpdate(
            oldCollection: $asset->getKeywords(),
            newCollection: $dto->getKeywords(),
        );
        $this->colUpdate(
            oldCollection: $asset->getAuthors(),
            newCollection: $dto->getAuthors(),
        );

        return $this->updateExisting($asset, $flush);
    }
}
