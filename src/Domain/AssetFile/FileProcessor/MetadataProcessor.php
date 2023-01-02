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

final class MetadataProcessor
{
    public function __construct(
        private readonly AssetMetadataProcessor $assetMetadataProvider,
        private readonly AssetMetadataAutocomplete $assetMetadataAutocomplete,
        private readonly AssetFileEventDispatcher $dispatcher,
        private readonly AssetFileManager $assetFileManager,
        private readonly ResourceLocker $resourceLocker,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function process(AssetFile $assetFile, BaseFile $file = null): AssetFile
    {
        $asset = $assetFile->getAsset();
        $this->assetMetadataProvider->provideMetaData($assetFile, $file);

        if ($asset->getAssetFlags()->isNotDescribed() && $asset->getAssetFlags()->isNotAutocompletedMetadata()) {
            $this->assetMetadataAutocomplete->autocompleteMetadata($assetFile);
        }

        $this->assetFileManager->updateExisting($assetFile);
        $this->resourceLocker->unLock(AssetMetadataProcessor::DATA_SUGGESTER_LOCK_NAME);
        $this->dispatcher->dispatchMetadataProcessed($assetFile);

        return $assetFile;
    }
}
