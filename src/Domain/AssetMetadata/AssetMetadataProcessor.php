<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Suggestion\DataSuggesterInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetMetadata\DataSuggestionsDto;
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
    public function readExifData(AssetFile $assetFile, BaseFile $file): array
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

        return $metadata;
    }

    /**
     * Creates missing authors and keywords; call it before the asset lock.
     *
     * @return array<string, DataSuggestionsDto>
     */
    public function provideSuggestions(AssetFile $assetFile, array $metadata): array
    {
        $suggestions = [];
        foreach ($this->dataSuggesters as $key => $dataSuggester) {
            if ($dataSuggester->supports($assetFile)) {
                $suggestions[$key] = $dataSuggester->provideSuggestions($assetFile, $metadata);
            }
        }

        return $suggestions;
    }

    /**
     * Call it under the asset lock, after the asset was refreshed.
     *
     * @param array<string, DataSuggestionsDto> $suggestions
     */
    public function applySuggestions(AssetFile $assetFile, array $suggestions): void
    {
        foreach ($this->dataSuggesters as $key => $dataSuggester) {
            if (isset($suggestions[$key]) && $dataSuggester->supports($assetFile)) {
                $dataSuggester->applySuggestions($assetFile, $suggestions[$key]);
            }
        }
    }
}
