<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Author;

/**
 * Decides which asset files must be single use: the licence flag, exif Credit or Source matching the configured
 * pattern, or a source of the asset with the flag. The SQL backfill reads the two flags in
 * `AssetFileDBALRepository::findNotSingleUseByLicence()` and decides through `mustBeSingleUse()` as well.
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
        if ($assetFile->getFlags()->isSingleUse() || false === $this->mustBeSingleUse(
            $assetFile->getLicence()->getFlags()->isSingleUseEnforced(),
            $assetFile->hasAsset() && $assetFile->getAsset()->getSources()->exists(
                static fn (int|string $key, Author $source): bool => $source->getFlags()->isSingleUseEnforced(),
            ),
            $assetFile->getMetadata()->getExifData(),
        )) {
            return false;
        }

        $assetFile->getFlags()->setSingleUse(true);

        return true;
    }

    /**
     * @param array<string, mixed> $exifData
     */
    public function mustBeSingleUse(bool $licenceEnforced, bool $sourceEnforced, array $exifData): bool
    {
        return $licenceEnforced || $sourceEnforced || $this->hasSingleUseMetadata($exifData);
    }

    /**
     * @param array<string, mixed> $exifData
     */
    private function hasSingleUseMetadata(array $exifData): bool
    {
        return App::EMPTY_STRING !== $this->singleUseMetadataPattern && array_any(
            self::SINGLE_USE_METADATA_TAGS,
            fn (string $tag): bool => is_string($exifData[$tag] ?? null) && 1 === preg_match($this->singleUseMetadataPattern, $exifData[$tag]),
        );
    }
}
