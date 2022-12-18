<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileVersionProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
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
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetFileHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly AssetFileVersionProvider $assetFileDefaultProvider
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        $type = ImageCropTag::tryFrom((string) $metadata->customType);

        if (null === $type) {
            throw new SerializerException(
                sprintf('(%s) should by provided as type', ImageCropTag::class)
            );
        }

        if ($value instanceof AssetFile) {
            return $this->serializer->toArray(
                $this->getAssetFileDecorator($value, $type)
            );
        }

        throw new SerializerException(sprintf('Value should be instance of (%s)', Asset::class));
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    public function getAssetFileDecorator(AssetFile $assetFile, ImageCropTag $type): AbstractEntityDto
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

    private function getImageDecorator(ImageFile $imageFile, ImageCropTag $type): AbstractEntityDto
    {
        return match ($type) {
            ImageCropTag::List => ImageFileAdmListDto::getInstance($imageFile),
            ImageCropTag::Detail => ImageFileAdmDetailDto::getInstance($imageFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };
    }

    private function getAudioDecorator(AudioFile $audioFile, ImageCropTag $type): AbstractEntityDto
    {
        return match ($type) {
            ImageCropTag::List => AudioFileAdmListDto::getInstance($audioFile),
            ImageCropTag::Detail => AudioFileAdmDetailDto::getInstance($audioFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };
    }

    private function getVideoDecorator(VideoFile $videoFile, ImageCropTag $type): AbstractEntityDto
    {
        return match ($type) {
            ImageCropTag::List => VideoFileAdmListDto::getInstance($videoFile),
            ImageCropTag::Detail => VideoFileAdmDetailDto::getInstance($videoFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };
    }

    private function getDocumentDecorator(DocumentFile $documentFile, ImageCropTag $type): AbstractEntityDto
    {
        return match ($type) {
            ImageCropTag::List => DocumentFileAdmListDto::getInstance($documentFile),
            ImageCropTag::Detail => DocumentFileAdmDetailDto::getInstance($documentFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };
    }
}
