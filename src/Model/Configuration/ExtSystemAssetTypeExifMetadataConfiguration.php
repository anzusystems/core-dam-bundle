<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemAssetTypeExifMetadataConfiguration
{
    public const ENABLED_KEY = 'enabled';
    public const REQUIRED_KEY = 'required';
    public const AUTOCOMPLETE_FROM_EXIF_METADATA_TAGS_KEY = 'autocomplete_from_exif_metadata_tags';

    public function __construct(
        private readonly bool $enabled,
        private readonly bool $required,
        private readonly array $autocompleteFromMetadataTags,
    ) {
    }

    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            $config[self::ENABLED_KEY] ?? false,
            $config[self::REQUIRED_KEY] ?? false,
            $config[self::AUTOCOMPLETE_FROM_EXIF_METADATA_TAGS_KEY] ?? [],
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<string, string> - key of Exif Metadata Tag, value of separator
     */
    public function getAutocompleteFromMetadataTags(): array
    {
        return $this->autocompleteFromMetadataTags;
    }
}
