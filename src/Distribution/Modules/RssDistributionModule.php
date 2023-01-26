<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

use AnzuSystems\CoreDamBundle\Distribution\AbstractDistributionModule;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\RssDistribution;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;

final class RssDistributionModule extends AbstractDistributionModule
{
    public function __construct(
    ) {
    }

    /**
     * @param JwDistribution $distribution
     *
     * @throws SerializerException
     * @throws FilesystemException
     */
    public function distribute(Distribution $distribution): void
    {
        // todo readonly
    }

    public function supportsAssetType(): array
    {
        return [
            AssetType::Audio,
        ];
    }

    public static function supportsDistributionResourceName(): string
    {
        return RssDistribution::getResourceName();
    }
}
