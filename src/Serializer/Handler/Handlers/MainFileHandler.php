<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileVersionProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\ORM\NonUniqueResultException;

final class MainFileHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly AssetFileVersionProvider $assetFileDefaultProvider
    ) {
    }

    /**
     * @throws SerializerException
     * @throws NonUniqueResultException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        $type = ImageCropTag::tryFrom((string) $metadata->customType);

        if (null === $type) {
            throw new SerializerException(
                sprintf('(%s) should by provided as type', ImageCropTag::class)
            );
        }

        if ($value instanceof Asset) {
            return match ($value->getAttributes()->getAssetType()) {
                AssetType::Image => $this->serializeImageDecorator($value, $type),
                AssetType::Video => $this->serializeVideoDecorator($value, $type),
                AssetType::Audio => $this->serializeAudioDecorator($value, $type),
                AssetType::Document => $this->serializeDocumentDecorator($value, $type),
            };
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

    /**
     * @throws SerializerException
     * @throws NonUniqueResultException
     */
    private function serializeImageDecorator(Asset $asset, ImageCropTag $type): ?array
    {
        $assetFile = $this->assetFileDefaultProvider->getDefaultFile($asset);
        if (false === ($assetFile instanceof ImageFile)) {
            return null;
        }

        $dto = match ($type) {
            ImageCropTag::List => ImageFileAdmListDto::getInstance($assetFile),
            ImageCropTag::Detail => ImageFileAdmDetailDto::getInstance($assetFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };

        return $this->serializer->toArray($dto);
    }

    /**
     * @throws SerializerException
     * @throws NonUniqueResultException
     */
    private function serializeAudioDecorator(Asset $asset, ImageCropTag $type): ?array
    {
        $assetFile = $this->assetFileDefaultProvider->getDefaultFile($asset);
        if (false === ($assetFile instanceof AudioFile)) {
            return null;
        }

        $dto = match ($type) {
            ImageCropTag::List => AudioFileAdmListDto::getInstance($assetFile),
            ImageCropTag::Detail => AudioFileAdmDetailDto::getInstance($assetFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };

        return $this->serializer->toArray($dto);
    }

    /**
     * @throws SerializerException
     * @throws NonUniqueResultException
     */
    private function serializeVideoDecorator(Asset $asset, ImageCropTag $type): ?array
    {
        $assetFile = $this->assetFileDefaultProvider->getDefaultFile($asset);
        if (false === ($assetFile instanceof VideoFile)) {
            return null;
        }

        $dto = match ($type) {
            ImageCropTag::List => VideoFileAdmListDto::getInstance($assetFile),
            ImageCropTag::Detail => VideoFileAdmDetailDto::getInstance($assetFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };

        return $this->serializer->toArray($dto);
    }

    /**
     * @throws SerializerException
     * @throws NonUniqueResultException
     */
    private function serializeDocumentDecorator(Asset $asset, ImageCropTag $type): ?array
    {
        $assetFile = $this->assetFileDefaultProvider->getDefaultFile($asset);
        if (false === ($assetFile instanceof DocumentFile)) {
            return null;
        }

        $dto = match ($type) {
            ImageCropTag::List => DocumentFileAdmListDto::getInstance($assetFile),
            ImageCropTag::Detail => DocumentFileAdmDetailDto::getInstance($assetFile),
            ImageCropTag::RoiExample => throw new DomainException('Not supported'),
        };

        return $this->serializer->toArray($dto);
    }
}
