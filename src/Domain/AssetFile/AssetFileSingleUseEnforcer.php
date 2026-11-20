<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;

/**
 * The one place deciding which asset files must be single use: the licence flag, exif Credit or Source
 * matching the configured pattern, or a source of the asset with the flag. Further rules belong here as well.
 */
final readonly class AssetFileSingleUseEnforcer
{
    private const array SINGLE_USE_METADATA_TAGS = ['Credit', 'Source'];

    public function __construct(
        private string $singleUseMetadataPattern = App::EMPTY_STRING,
    ) {
    }

    public function enforce(AssetFile $assetFile): bool
    {
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse($assetFile)) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);

        return true;
    }

    /**
     * @param array<string, mixed> $exifData
     */
    public function hasSingleUseMetadata(array $exifData): bool
    {
        if (App::EMPTY_STRING === $this->singleUseMetadataPattern) {
            return false;
        }

        foreach (self::SINGLE_USE_METADATA_TAGS as $tag) {
            if (is_string($exifData[$tag] ?? null) && 1 === preg_match($this->singleUseMetadataPattern, $exifData[$tag])) {
                return true;
            }
        }

        return false;
    }

    private function mustBeSingleUse(AssetFile $assetFile): bool
    {
        return $assetFile->getLicence()->getFlags()->isSingleUseEnforced()
            || $this->hasSingleUseMetadata($assetFile->getMetadata()->getExifData())
            || ($assetFile->hasAsset() && $assetFile->getAsset()->getSources()->exists(
                static fn (int|string $key, Author $source): bool => $source->getFlags()->isSingleUseEnforced(),
            ));
    }
}
