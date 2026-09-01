<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion\DataSuggesterInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\File\File as BaseFile;

final class AssetMetadataProcessor
{
    private const string DATA_SUGGESTER_LOCK_PREFIX = 'lock_suggester_';

    /**
     * @var iterable<DataSuggesterInterface>
     */
    private readonly iterable $dataSuggesters;

    public function __construct(
        private readonly array $exifImageMetadata,
        private readonly array $exifCommonMetadata,
        private readonly Exiftool $exiftool,
        private readonly ResourceLocker $resourceLocker,
        private readonly ExifMetadataFilter $exifMetadataFilter,
        #[AutowireIterator(tag: DataSuggesterInterface::class, indexAttribute: 'key')]
        iterable $dataSuggesters,
    ) {
        $this->dataSuggesters = $dataSuggesters;
    }

    /**
     * @throws SerializerException
     */
    public function provideMetaData(AssetFile $assetFile, BaseFile $file): AssetFile
    {
        $rawMetadata = $this->exiftool->getTags($file->getRealPath());

        $metadata = $this->exifMetadataFilter->filter($rawMetadata, $this->exifCommonMetadata);
        if ($assetFile instanceof ImageFile) {
            $metadata = array_merge(
                $metadata,
                $this->exifMetadataFilter->filter($rawMetadata, $this->exifImageMetadata)
            );
        }
        $assetFile->getMetadata()->setExifData($metadata);
        $assetFile->getFlags()->setProcessedMetadata(true);

        $this->runSuggesters($assetFile, $metadata);

        return $assetFile;
    }

    // Per-asset scope; author/keyword creation has its own per-name lock in the facades.
    private function runSuggesters(AssetFile $assetFile, array $metadata): void
    {
        $lockName = self::DATA_SUGGESTER_LOCK_PREFIX . (string) $assetFile->getAsset()->getId();
        $this->resourceLocker->lock($lockName);

        try {
            foreach ($this->dataSuggesters as $dataSuggester) {
                if ($dataSuggester->supports($assetFile)) {
                    $dataSuggester->suggest($assetFile, $metadata);
                }
            }
        } finally {
            $this->resourceLocker->unLock($lockName);
        }
    }

}
