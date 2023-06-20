<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemIndexableInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\Language;

final class IndexSettings
{
    public function __construct(
        private readonly ConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @psalm-return class-string
     *
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function getEntityClassName(string $indexName): string
    {
        return App::ENTITY_NAMESPACE . '\\' . ucfirst($indexName);
    }

    public function getPrefixedIndexName(string $indexName): string
    {
        return $this->configurationProvider->getSettings()->getElasticIndexPrefix() . '_' . strtolower($indexName);
    }

    public function getFullIndexNameBySlug(string $indexName, string $slug): string
    {
        return $this->getPrefixedIndexName($indexName) . '_' . strtolower($slug);
    }

    public function getFullIndexNameByEntity(ExtSystemIndexableInterface $entity): string
    {
        return $this->getFullIndexNameBySlug($entity::getResourceName(), $entity->getExtSystem()->getSlug());
    }

    public function hasElasticLanguageDictionary(Language $language): bool
    {
        return in_array(
            $language->toString(),
            $this->configurationProvider->getSettings()->getElasticLanguageDictionaries(),
            true
        );
    }

    public function hasNotElasticLanguageDictionary(Language $language): bool
    {
        return false === $this->hasElasticLanguageDictionary($language);
    }
}
