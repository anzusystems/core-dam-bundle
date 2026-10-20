<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataAutocomplete;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Event\Dispatcher\AssetFileEventDispatcher;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\File\File as BaseFile;

final readonly class MetadataProcessor
{
    private const string DATA_SUGGESTER_LOCK_PREFIX = 'lock_suggester_';

    public function __construct(
        private AssetMetadataProcessor $assetMetadataProvider,
        private AssetMetadataAutocomplete $assetMetadataAutocomplete,
        private AssetFileEventDispatcher $dispatcher,
        private AssetFileManager $assetFileManager,
        private ResourceLocker $resourceLocker,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, BaseFile $file): AssetFile
    {
        $asset = $assetFile->getAsset();

        // Per-asset scope; author/keyword creation has its own per-name lock in the facades.
        $lockName = self::DATA_SUGGESTER_LOCK_PREFIX . (string) $asset->getId();
        $this->resourceLocker->lock($lockName);

        try {
            $this->assetMetadataProvider->provideMetaData($assetFile, $file);

            if ($asset->getAssetFlags()->isNotDescribed() && $asset->getAssetFlags()->isNotAutocompletedMetadata()) {
                $this->assetMetadataAutocomplete->autocompleteMetadata($assetFile);
            }

            $this->assetFileManager->updateExisting(
                assetFile: $assetFile,
                trackModification: false
            );
        } finally {
            $this->resourceLocker->unLock($lockName);
        }

        $this->dispatcher->dispatchMetadataProcessed($assetFile);

        return $assetFile;
    }
}
