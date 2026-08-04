<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\QueryFactory;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;

interface AssetFulltextQueryBuilderInterface
{
    /**
     * @param list<string> $customDataFields
     *
     * @return array<string, mixed>
     */
    public function build(string $text, array $customDataFields, ExtSystem $extSystem): array;
}
