<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexDefinition;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface IndexDefinitionExtensionInterface
{
    public function supports(string $indexName, string $extSystemSlug): bool;

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function extend(string $indexName, string $extSystemSlug, array $definition): array;
}
