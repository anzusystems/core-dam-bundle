<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Entity\Keyword;

final class KeywordIndexFactory implements IndexFactoryInterface
{
    public static function getDefaultKeyName(): string
    {
        return Keyword::class;
    }

    /**
     * @param Keyword $entity
     */
    public function buildFromEntity(ExtSystemIndexableInterface $entity): array
    {
        return [
            'id' => $entity->getId(),
            'reviewed' => $entity->getFlags()->isReviewed(),
            'name' => $entity->getName(),
        ];
    }
}
