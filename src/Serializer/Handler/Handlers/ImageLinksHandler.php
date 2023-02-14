<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\ORM\NonUniqueResultException;

class ImageLinksHandler extends AbstractHandler
{
    private const LINKS_TYPE = 'image';

    public function __construct(
        protected readonly RegionOfInterestRepository $roiRepository,
        protected readonly ImageUrlFactory $imageUrlFactory,
        protected readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        if (null === $value) {
            return null;
        }
        $type = ImageCropTag::tryFrom((string) $metadata->customType);
        if (null === $type) {
            throw new SerializerException(
                sprintf('(%s) should by provided as type', ImageCropTag::class)
            );
        }

        if (false === ($value instanceof ImageFile)) {
            throw new SerializerException(sprintf('Value should be instance of (%s)', ImageFile::class));
        }

        return $this->getImageLinkUrl($value, $type);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getImageLinkUrl(ImageFile $imageFile, ImageCropTag $cropTag): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $sizeList = $this->configurationProvider->getImageAdminSizeList($cropTag->toString());
        if (empty($sizeList)) {
            return [];
        }

        return [
            $this->getKey($cropTag) => $this->serializeImageCrop($imageFile, $sizeList[array_key_first($sizeList)]),
        ];
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    protected function getKey(ImageCropTag $cropTag): string
    {
        return self::LINKS_TYPE . '_' . $cropTag->toString();
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function serializeImageCrop(ImageFile $imageFile, CropAllowItem $item): array
    {
        $reqCrop = (new RequestedCropDto())
            ->setRequestWidth($item->getWidth())
            ->setRequestHeight($item->getHeight())
            ->setRoi(RegionOfInterest::FIRST_ROI_POSITION);

        $imageFileId = (string) $imageFile->getId();
        $roi = $this->roiRepository->findByImageIdAndPosition($imageFileId, RegionOfInterest::FIRST_ROI_POSITION);
        if (null === $roi) {
            return [];
        }

        return [
            'type' => self::LINKS_TYPE,
            'url' => $this->configurationProvider->getAdminDomain() . $this->imageUrlFactory->generatePublicUrl(
                imageId: $imageFileId,
                width: $reqCrop->getRequestWidth(),
                height: $reqCrop->getRequestHeight(),
                roiPosition: $roi->getPosition()
            ),
            'requestedWidth' => $reqCrop->getRequestWidth(),
            'requestedHeight' => $reqCrop->getRequestHeight(),
            'title' => empty($item->getTitle())
                ? "{$reqCrop->getRequestWidth()}x{$reqCrop->getRequestHeight()}"
                : $item->getTitle(),
        ];
    }
}
