<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CoreDamBundle\Helper\StringHelper;

/**
 * Applies the configured allow list and value cap identically for every ingest path (app upload and batch importers).
 */
final readonly class ExifMetadataFilter
{
    private const int MAX_VALUE_LENGTH = 5_000;

    /**
     * @param array<string, string> $rawMetadata
     * @param array<string, mixed> $allowedMetadataList
     *
     * @return array<string, string>
     */
    public function filter(array $rawMetadata, array $allowedMetadataList): array
    {
        $metadata = [];
        foreach ($allowedMetadataList as $metadataName => $value) {
            if (isset($rawMetadata[$metadataName])) {
                $metadata[$metadataName] = $this->parseValue((string) $rawMetadata[$metadataName]);
            }
        }

        return $metadata;
    }

    // No HTML escaping: values go to JSON/ES only, never raw HTML; escaping here double-encoded & and '.
    private function parseValue(string $value): string
    {
        return StringHelper::parseString($value, self::MAX_VALUE_LENGTH);
    }
}
