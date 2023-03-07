<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

#[AppAssert\AssetDtoConstraint]
final class AssetAdmUpdateDto extends AbstractEntityDto
{
    protected string $resourceName = Asset::class;
    protected Asset $asset;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?DistributionCategory $distributionCategory = null;

    public static function getInstance(Asset $asset): static
    {
        return parent::getBaseInstance($asset)
            ->setAsset($asset)
            ->setDistributionCategory($asset->getDistributionCategory());
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): self
    {
        $this->asset = $asset;

        return $this;
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
