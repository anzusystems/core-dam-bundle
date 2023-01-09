<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

final class AssetAdmUpdateDto extends AbstractEntityDto
{
    protected string $resourceName = Asset::class;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?DistributionCategory $distributionCategory;

    public static function getInstance(Asset $asset): static
    {
        return parent::getBaseInstance($asset)
            ->setDistributionCategory($asset->getDistributionCategory());
    }

    public function getDistributionCategory(): ?DistributionCategory
    {
        return $this->distributionCategory;
    }

    public function setDistributionCategory(?DistributionCategory $distributionCategory): self
    {
        $this->distributionCategory = $distributionCategory;

        return $this;
    }
}
