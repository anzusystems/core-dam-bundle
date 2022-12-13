<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DistributionModuleInterface
{
    public function distribute(Distribution $distribution): void;

    /**
     * @return array<int, AssetType>
     */
    public function supportsAssetType(): array;

    /**
     * Checks if current distribution service is authenticated
     */
    public function isAuthenticated(string $distributionService): bool;

    /**
     * @psalm-return class-string
     */
    public static function getDefaultKeyName(): string;

    /**
     * @psalm-return class-string
     */
    public static function supportsDistributionResourceName(): string;
}
