<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata\DataSuggestionsDto;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DataSuggesterInterface
{
    /**
     * Runs before the asset lock: creates missing entities, must not modify the Asset or its AssetMetadata.
     */
    public function provideSuggestions(AssetFile $assetFile, array $metadata): DataSuggestionsDto;

    /**
     * Runs under the asset lock, after the asset was refreshed.
     */
    public function applySuggestions(AssetFile $assetFile, DataSuggestionsDto $suggestions): void;

    public function supports(AssetFile $assetFile): bool;
}
