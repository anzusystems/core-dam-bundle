<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Interfaces;


use AnzuSystems\Contracts\Entity\Interfaces\IndexableInterface;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexFactory\DBALIndexFactoryInterface;
use App\Elasticsearch\Repository\DBALIndexableRepositoryInterface;

interface DBALIndexableInterface extends IndexableInterface
{
    /**
     * @return class-string<DBALIndexFactoryInterface>
     */
    public static function getDBALIndexFactoryClassName(): string;

    /**
     * @return class-string<DBALIndexableRepositoryInterface>
     */
    public static function getRepositoryClassName(): string;
}
