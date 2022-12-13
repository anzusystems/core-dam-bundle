<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetMetadata;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;

final class AssetMetadataManager extends AbstractManager
{
    public function create(AssetMetadata $assetMetadata, bool $flush = true): AssetMetadata
    {
        $this->trackCreation($assetMetadata);
        $this->entityManager->persist($assetMetadata);
        $this->flush($flush);

        return $assetMetadata;
    }

    public function updateFromMetadataBulkDto(
        AssetMetadata $assetMetadata,
        FormProvidableMetadataBulkUpdateDto $dto,
        bool $flush = true
    ): AssetMetadata {
        $this->trackModification($assetMetadata);
        $assetMetadata->setCustomData($dto->getCustomData());
        $this->flush($flush);

        return $assetMetadata;
    }

    public function removeSuggestions(AssetMetadata $assetMetadata, bool $flush = true): AssetMetadata
    {
        $this->trackModification($assetMetadata);
        $assetMetadata
            ->setAuthorSuggestions([])
            ->setKeywordSuggestions([])
        ;
        $this->flush($flush);

        return $assetMetadata;
    }
}
