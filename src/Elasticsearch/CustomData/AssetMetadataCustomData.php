<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\CustomData;

use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormProvider;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition\CustomDataIndexDefinitionFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\NonUniqueResultException;

final class AssetMetadataCustomData
{
    private const METADATA_PREFIX = 'custom_data_';

    /**
     * @var array<string, ReadableCollection<int, CustomFormElement>>
     */
    private array $searchableElementsCache = [];

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
                $entity->getMetadata()->getCustomData()[$searchableElement->getKey()] ?? null;
        }

        return $data;
    }

    /**
     * @return ReadableCollection<int, CustomFormElement>
     *
     * @throws NonUniqueResultException
     */
    private function getSearchableElements(Asset $entity): ReadableCollection
    {
        $cacheKey = sprintf('%s_%s', $entity->getExtSystem()->getSlug(), $entity->getAssetType()->toString());

        if (false === isset($this->searchableElementsCache[$cacheKey])) {
            $this->searchableElementsCache[$cacheKey] = $this->customFormProvider->provideFormSearchableElements(
                $this->customFormProvider->provideFormByAssetProvidable($entity)
            );
        }

        return $this->searchableElementsCache[$cacheKey];
    }
}
