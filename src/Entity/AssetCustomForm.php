<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetCustomFormRepository::class)]
#[ORM\Index(fields: ['extSystem', 'assetType'], name: 'IDX_ext_system_asset_type')]
class AssetCustomForm extends CustomForm implements ExtSystemInterface
{
    #[ORM\ManyToOne(targetEntity: ExtSystem::class)]
    private ExtSystem $extSystem;

    #[ORM\Column(enumType: AssetType::class)]
    private AssetType $assetType;

    public function __construct()
    {
        parent::__construct();
        $this->setAssetType(AssetType::Default);
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
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
}
