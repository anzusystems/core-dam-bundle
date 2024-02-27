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
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class LicenceCollectionHandler extends AbstractHandler
{
    use SerializerAwareTrait;

    public const int MAX_IDS = 30;

    public function __construct(
        private readonly EntityIdHandler $entityIdHandler,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata): string
    {
        throw new SerializerException('Serialization not supported');
    }

    public function deserialize(mixed $value, Metadata $metadata): Collection
    {
        if (empty($value)) {
            return new ArrayCollection([]);
        }

        if (is_string($value)) {
            $ids = array_map(
                fn (string $item): int => (int) $item,
                explode(',', $value)
            );

            if (count($ids) > self::MAX_IDS) {
                throw new SerializerException('Licence collection size ');
            }

            return $this->entityIdHandler->deserialize($ids, $metadata);
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }
}
