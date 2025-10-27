<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\CustomData;

use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder\StringIndexBuilder;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
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
            $data[CustomDataIndexDefinitionFactory::getIndexKeyNameByElement($searchableElement)] =
                $entity->getMetadata()->getCustomData()[$searchableElement->getProperty()] ?? null;
        }

        if ($entity->getAttributes()->getAssetType()->is(AssetType::Image)) {
            $data = StringIndexBuilder::optimizeImageCustomData($data);
        }

        return $data;
    }
}
