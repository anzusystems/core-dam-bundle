<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;

final class AuthorIndexFactory implements IndexFactoryInterface
{
    public static function getDefaultKeyName(): string
    {
        return Author::class;
    }

    /**
     * @param Author $entity
     */
    public function buildFromEntity(ExtSystemIndexableInterface $entity): array
    {
        return [
            'id' => $entity->getId(),
            'identifier' => $entity->getIdentifier(),
            'reviewed' => $entity->getFlags()->isReviewed(),
            'name' => $entity->getName(),
            'type' => $entity->getType()->toString(),
            'createdAt' => $entity->getCreatedAt()->getTimestamp(),
        ];
    }
}
