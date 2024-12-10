<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion;

use AnzuSystems\CoreDamBundle\Model\Configuration\ExtSystemAssetTypeExifMetadataConfiguration;

final class SuggestionTagParser
{
    /**
     * @return array<string>
     */
    public function parse(ExtSystemAssetTypeExifMetadataConfiguration $configuration, array $metadata): array
    {
        foreach ($configuration->getAutocompleteFromMetadataTags() as $tagName => $separator) {
            dump($tagName);

            // 1. Tag not found or empty, continue to the next one
            if (empty($metadata[$tagName])) {
                continue;
            }
            // 2. Separator not defined, take it as a single value field
            if (empty($separator)) {
                return [$metadata[$tagName]];
            }
            // 3. Separate the value by defined separator and return trimmed values.
            $tags = explode(
                separator: $separator,
                string: $metadata[$tagName]
            );

            return array_map('trim', $tags);
        }

        return [];
    }
}
