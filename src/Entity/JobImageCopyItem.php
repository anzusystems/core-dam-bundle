<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobImageCopyItemRepository::class)]
#[ORM\Index(name: 'STATUS_IDX', fields: ['status'])]
class JobImageCopyItem implements IdentifiableInterface
{
    use IdentityIntTrait;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private Asset $sourceAsset;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    private ?Asset $targetAsset = null;

    #[ORM\Column(enumType: AssetFileCopyStatus::class)]
    private AssetFileCopyStatus $status;

    #[ORM\ManyToOne(targetEntity: JobImageCopy::class, inversedBy: 'items')]
    private JobImageCopy $job;

    public function __construct()
    {
        $this->setSourceAsset(new Asset());
        $this->setTargetAsset(null);
        $this->setStatus(AssetFileCopyStatus::Default);
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

    public function getStatus(): AssetFileCopyStatus
    {
        return $this->status;
    }

    public function setStatus(AssetFileCopyStatus $status): self
    {
        $this->status = $status;

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
