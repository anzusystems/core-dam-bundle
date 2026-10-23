<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion\DataSuggesterInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\File\File as BaseFile;

final class AssetMetadataProcessor
{
    /**
     * @var iterable<DataSuggesterInterface>
     */
    private readonly iterable $dataSuggesters;

    public function __construct(
        private readonly array $exifImageMetadata,
        private readonly array $exifCommonMetadata,
        private readonly Exiftool $exiftool,
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

        $this->runSuggesters($assetFile, $metadata);

        return $assetFile;
    }

    private function runSuggesters(AssetFile $assetFile, array $metadata): void
    {
        foreach ($this->dataSuggesters as $dataSuggester) {
            if ($dataSuggester->supports($assetFile)) {
                $dataSuggester->suggest($assetFile, $metadata);
            }
        }
    }
}
