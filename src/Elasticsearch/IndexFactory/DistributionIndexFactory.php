<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;

final readonly class DistributionIndexFactory implements IndexFactoryInterface
{
    public static function getDefaultKeyName(): string
    {
        return Distribution::class;
    }

    /**
     * @param Distribution $entity
     */
    public function buildFromEntity(ExtSystemIndexableInterface $entity): array
    {
        return [
            'id' => $entity->getId(),
            'extId' => $entity->getExtId(),
            'service' => $entity->getDiscriminator(),
            'serviceSlug' => $entity->getDistributionService(),
            'status' => $entity->getStatus()->toString(),
            'assetId' => $entity->getAsset()?->getId(),
            'assetFileId' => $entity->getAssetFile()?->getId(),
            'licenceId' => $entity->getAssetFile()?->getLicence()?->getId(),
            'createdAt' => $entity->getCreatedAt()->getTimestamp(),
        ];
    }
}
