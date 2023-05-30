<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\CropAllowItem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\ORM\NonUniqueResultException;

class ImageLinksHandler extends AbstractHandler
{
    public const TAG_LIST = 'list';
    public const TAG_DETAIL = 'detail';
    public const TAG_TABLE = 'table';
    public const TAG_ROI_EXAMPLE = 'roi_example';
    public const LIST_LINKS_TAGS = self::TAG_LIST . ',' . self::TAG_TABLE;
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
        if (null === $value || null === $metadata->customType) {
            return null;
        }

        if (false === ($value instanceof ImageFile)) {
            throw new SerializerException(sprintf('Value should be instance of (%s)', ImageFile::class));
        }

        return $this->getImageLinkUrl($value, explode(',', $metadata->customType));
    }

    /**
     * @param string[] $tags
     */
    public function getImageLinkUrl(ImageFile $imageFile, array $tags): array
    {
        if ($imageFile->getAssetAttributes()->getStatus()->isNot(AssetFileProcessStatus::Processed)) {
            return [];
        }

        $res = [];
        foreach ($tags as $tag) {
            $sizeList = $this->configurationProvider->getImageAdminSizeList($tag);

            if (empty($sizeList)) {
                continue;
            }

            $res[$this->getKey($tag)] = $this->serializeImageCrop($imageFile, $sizeList[array_key_first($sizeList)]);
        }

        return $res;
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    protected function getKey(string $tag): string
    {
        return self::LINKS_TYPE . '_' . $tag;
    }

    protected function serializeImageCrop(ImageFile $imageFile, CropAllowItem $item): array
    {
        $imageId = (string) $imageFile->getId();

        return [
            'type' => self::LINKS_TYPE,
            'url' => $this->configurationProvider->getAdminDomain() . $this->imageUrlFactory->generateAllowListUrl(
                imageId: $imageId,
                item: $item,
                roiPosition: 0
            ),
            'requestedWidth' => $item->getWidth(),
            'requestedHeight' => $item->getHeight(),
            'title' => empty($item->getTitle())
                ? "{$item->getWidth()}x{$item->getHeight()}"
                : $item->getTitle(),
        ];
    }
}
