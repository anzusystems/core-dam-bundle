<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DistributionManagerInterface
{
    public function create(Distribution $distribution, bool $flush = true): Distribution;

    public function update(Distribution $distribution, Distribution $newDistribution, bool $flush = true): Distribution;

    public function updateExisting(Distribution $distribution, bool $flush = true): Distribution;

    public static function getDefaultKeyName(): string;

    public function delete(Distribution $distribution, bool $flush = true): bool;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
