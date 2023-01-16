<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFactory;
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

final class ImageLinksHandler extends AbstractHandler
{
    private const LINKS_TYPE = 'image';

    public function __construct(
        private readonly CropFactory $cropFactory,
        private readonly RegionOfInterestRepository $roiRepository,
        private readonly ImageUrlFactory $imageUrlFactory,
        private readonly ConfigurationProvider $configurationProvider,
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

        return $this->getImageLinkUrl($value, [$type]);
    }

    /**
     * @param array<int, ImageCropTag> $tags
     *
     * @throws NonUniqueResultException
     */
    public function getImageLinkUrl(ImageFile $imageFile, array $tags): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $links = [];
        foreach ($tags as $tag) {
            $serializedTag = $this->getFirstTag($imageFile, $tag);
            if ($serializedTag) {
                $links[self::LINKS_TYPE . '_' . $tag->toString()] = $serializedTag;
            }
        }

        return $links;
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    /**
     * @throws NonUniqueResultException
     */
    private function getFirstTag(ImageFile $imageFile, ImageCropTag $cropTag): ?array
    {
        foreach ($this->configurationProvider->getImageAdminSizeList($cropTag) as $allowItem) {
            return $this->serializeCropAllowItem($imageFile, $allowItem);
        }

        return null;
    }

    /**
     * @throws NonUniqueResultException
     */
    private function serializeCropAllowItem(ImageFile $imageFile, CropAllowItem $item): array
    {
        $reqCrop = (new RequestedCropDto())
            ->setRequestWidth($item->getWidth())
            ->setRequestHeight($item->getHeight())
            ->setRoi(RegionOfInterest::FIRST_ROI_POSITION);

        $roi = $this->roiRepository->findByImageIdAndPosition($imageFile->getId(), RegionOfInterest::FIRST_ROI_POSITION);
        if (null === $roi) {
            return [];
        }

        return [
            'type' => self::LINKS_TYPE,
            'url' => $this->configurationProvider->getAdminDomain() . $this->imageUrlFactory->generatePublicUrl(
                imageId: $imageFile->getId(),
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
