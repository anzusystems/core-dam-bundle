<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory;

use AnzuSystems\CoreDamBundle\Elasticsearch\Exception\InvalidRecordException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DBALIndexFactoryInterface
{
    /**
     * @throws InvalidRecordException
     */
    public function buildFromArray(array $array): array;
}
