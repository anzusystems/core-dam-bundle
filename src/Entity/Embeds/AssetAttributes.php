<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetAttributes
{
    #[ORM\Column(enumType: AssetType::class)]
    private AssetType $assetType;

    #[ORM\Column(enumType: AssetStatus::class)]
    private AssetStatus $status;

    public function __construct()
    {
        $this->setAssetType(AssetType::Default);
        $this->setStatus(AssetStatus::Default);
    }

    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function setAssetType(AssetType $assetType): self
    {
        $this->assetType = $assetType;

        return $this;
    }

    public function getStatus(): AssetStatus
    {
        return $this->status;
    }

    public function setStatus(AssetStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
