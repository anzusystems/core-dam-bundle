<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAnzuDBALRepository implements AnzuDBALRepositoryInterface
{
    protected Connection $connection;

    #[Required]
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }
}
