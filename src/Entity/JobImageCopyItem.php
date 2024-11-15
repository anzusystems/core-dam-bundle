<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyResult;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobImageCopyItemRepository::class)]
class JobImageCopyItem implements IdentifiableInterface
{
    use IdentityIntTrait;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private Asset $sourceAsset;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private ?Asset $targetAsset = null;

    #[ORM\Column(enumType: AssetFileCopyResult::class)]
    private AssetFileCopyResult $result;

    #[ORM\ManyToOne(targetEntity: JobImageCopy::class, inversedBy: 'items')]
    private JobImageCopy $job;

    public function __construct()
    {
        $this->setSourceAsset(new Asset());
        $this->setTargetAsset(null);
        $this->setResult(AssetFileCopyResult::Default);
        $this->setJob(new JobImageCopy());
    }

    public function getSourceAsset(): Asset
    {
        return $this->sourceAsset;
    }

    public function setSourceAsset(Asset $sourceAsset): self
    {
        $this->sourceAsset = $sourceAsset;

        return $this;
    }

    public function getTargetAsset(): ?Asset
    {
        return $this->targetAsset;
    }

    public function setTargetAsset(?Asset $targetAsset): self
    {
        $this->targetAsset = $targetAsset;

        return $this;
    }

    public function getResult(): AssetFileCopyResult
    {
        return $this->result;
    }

    public function setResult(AssetFileCopyResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getJob(): JobImageCopy
    {
        return $this->job;
    }

    public function setJob(JobImageCopy $job): self
    {
        $this->job = $job;

        return $this;
    }
}
