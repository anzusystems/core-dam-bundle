<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DataSuggesterInterface
{
    public function suggest(AssetFile $assetFile, array $metadata): void;

    public function supports(AssetFile $assetFile): bool;
}
