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
    public const string DATA_SUGGESTER_LOCK_NAME = 'lock_suggester';

    /**
     * @var iterable<DataSuggesterInterface>
     */
    private readonly iterable $dataSuggesters;

    public function __construct(
        private readonly array $exifImageMetadata,
        private readonly array $exifCommonMetadata,
        private readonly Exiftool $exiftool,
        private readonly ResourceLocker $resourceLocker,
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

        $metadata = $this->provideCommonMetadata($rawMetadata, $this->exifCommonMetadata);
        if ($assetFile instanceof ImageFile) {
            $metadata = array_merge(
                $metadata,
                $this->provideCommonMetadata($rawMetadata, $this->exifImageMetadata)
            );
        }
        $assetFile->getMetadata()->setExifData($metadata);
        $assetFile->getFlags()->setProcessedMetadata(true);

        if ($this->resourceLocker->lock(self::DATA_SUGGESTER_LOCK_NAME)) {
            foreach ($this->dataSuggesters as $dataSuggester) {
                if ($dataSuggester->supports($assetFile)) {
                    $dataSuggester->suggest($assetFile, $metadata);
                }
            }
        }

        return $assetFile;
    }

    private function provideCommonMetadata(array $rawMetadata, array $allowedMetadataList): array
    {
        $metadata = [];
        foreach ($allowedMetadataList as $metadataName => $value) {
            if (isset($rawMetadata[$metadataName])) {
                $metadata[$metadataName] = $this->parseValue($rawMetadata[$metadataName]);
            }
        }

        return $metadata;
    }

    private function parseValue(string $value): string
    {
        return htmlspecialchars(strip_tags($value), ENT_SUBSTITUTE);
    }
}
