<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ApiViewType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetFileHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    /**
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): ?array
    {
        if (null === $value) {
            return null;
        }

        $type = ApiViewType::tryFrom((string) $metadata->customType);

        if (null === $type) {
            throw new SerializerException(
                sprintf('(%s) should by provided as type', ApiViewType::class)
            );
        }

        if ($value instanceof AssetFile) {
            return $this->serializer->toArray(
                $this->getAssetFileDecorator($value, $type)
            );
        }

        throw new SerializerException(sprintf(
            'Value should be instance of (%s)',
            AssetFile::class,
        ));
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    public function getAssetFileDecorator(AssetFile $assetFile, ApiViewType $type): AbstractEntityDto
    {
        if ($assetFile instanceof ImageFile) {
            return $this->getImageDecorator($assetFile, $type);
        }
        if ($assetFile instanceof DocumentFile) {
            return $this->getDocumentDecorator($assetFile, $type);
        }
        if ($assetFile instanceof VideoFile) {
            return $this->getVideoDecorator($assetFile, $type);
        }
        if ($assetFile instanceof AudioFile) {
            return $this->getAudioDecorator($assetFile, $type);
        }

        throw new DomainException('Not supported');
    }

    private function getImageDecorator(ImageFile $imageFile, ApiViewType $type): AbstractEntityDto
    {
        return match ($type) {
            ApiViewType::List => ImageFileAdmListDto::getInstance($imageFile),
            ApiViewType::Detail => ImageFileAdmDetailDto::getInstance($imageFile),
        };
    }

    private function getAudioDecorator(AudioFile $audioFile, ApiViewType $type): AbstractEntityDto
    {
        return match ($type) {
            ApiViewType::List => AudioFileAdmListDto::getInstance($audioFile),
            ApiViewType::Detail => AudioFileAdmDetailDto::getInstance($audioFile),
        };
    }

    private function getVideoDecorator(VideoFile $videoFile, ApiViewType $type): AbstractEntityDto
    {
        return match ($type) {
            ApiViewType::List => VideoFileAdmListDto::getInstance($videoFile),
            ApiViewType::Detail => VideoFileAdmDetailDto::getInstance($videoFile),
        };
    }

    private function getDocumentDecorator(DocumentFile $documentFile, ApiViewType $type): AbstractEntityDto
    {
        return match ($type) {
            ApiViewType::List => DocumentFileAdmListDto::getInstance($documentFile),
            ApiViewType::Detail => DocumentFileAdmDetailDto::getInstance($documentFile),
        };
    }
}
