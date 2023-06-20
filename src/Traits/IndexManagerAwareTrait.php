<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use Symfony\Contracts\Service\Attribute\Required;

trait IndexManagerAwareTrait
{
    protected IndexManager $indexManager;

    #[Required]
    public function setIndexManager(IndexManager $indexManager): void
    {
        $this->indexManager = $indexManager;
    }
}
