<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataAutocomplete;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataProcessor;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetSuggesterLocker;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\File\File as BaseFile;

final readonly class MetadataProcessor
{
    public function __construct(
        private AssetMetadataProcessor $assetMetadataProvider,
        private AssetMetadataAutocomplete $assetMetadataAutocomplete,
        private AssetFileEventDispatcher $dispatcher,
        private AssetFileManager $assetFileManager,
        private AssetSuggesterLocker $assetSuggesterLocker,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, BaseFile $file): AssetFile
    {
        $asset = $assetFile->getAsset();
        $exifData = $this->assetMetadataProvider->readExifData($assetFile, $file);

        $suggestions = $this->assetMetadataProvider->provideSuggestions($assetFile, $exifData);

        // Per-asset scope: refresh, suggestions, autocomplete and flush. Authors and keywords are created before it.
        try {
            $this->assetSuggesterLocker->lock($asset);
            $this->assetMetadataProvider->applySuggestions($assetFile, $suggestions);

            if ($asset->getAssetFlags()->isNotDescribed() && $asset->getAssetFlags()->isNotAutocompletedMetadata()) {
                $this->assetMetadataAutocomplete->autocompleteMetadata($assetFile);
            }

            $assetFile->getFlags()->setProcessedMetadata(true);
            $this->assetFileManager->updateExisting(
                assetFile: $assetFile,
                trackModification: false
            );
        } finally {
            $this->assetSuggesterLocker->unLock($asset);
        }

        $this->dispatcher->dispatchMetadataProcessed($assetFile);

        return $assetFile;
    }
}
