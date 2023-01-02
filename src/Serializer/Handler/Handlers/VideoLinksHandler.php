<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Doctrine\ORM\NonUniqueResultException;

final class VideoLinksHandler extends AbstractHandler
{
    public function __construct(
        private readonly ImageLinksHandler $imageLinksHandler,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws SerializerException
     */
    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        if ($value instanceof VideoFile) {
            if (null === $value?->getPreviewImage()?->getMainFile()) {
                return [];
            }

            return $this->imageLinksHandler->serialize($value->getPreviewImage()->getMainFile(), $metadata);
        }

        throw new SerializerException(sprintf('Value should be instance of (%s)', VideoFile::class));
    }

    /**
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }
}
