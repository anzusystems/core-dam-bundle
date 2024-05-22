<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\CustomData;

use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\ORM\NonUniqueResultException;

final class AssetMetadataCustomData
{
    public function __construct(
        private readonly CustomFormProvider $customFormProvider
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function buildFromEntity(Asset $entity): array
    {
        $data = [];
        $searchableElements = $this->customFormProvider->provideFormSearchableElements(
            $this->customFormProvider->provideFormByAssetProvidable($entity)
        );

        foreach ($searchableElements as $searchableElement) {
            $data[CustomDataIndexDefinitionFactory::getIndexKeyName($searchableElement)] =
                $entity->getMetadata()->getCustomData()[$searchableElement->getProperty()] ?? null;
        }

        return $data;
    }
}
