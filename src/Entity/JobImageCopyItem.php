<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Repository\JobImageCopyItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobImageCopyItemRepository::class)]
#[ORM\Index(name: 'STATUS_IDX', fields: ['status'])]
class JobImageCopyItem implements IdentifiableInterface
{
    use IdentityIntTrait;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $sourceAssetId = '';

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $targetAssetId = null;

    #[ORM\Column(enumType: AssetFileCopyStatus::class)]
    private AssetFileCopyStatus $status;

    #[ORM\ManyToOne(targetEntity: JobImageCopy::class, inversedBy: 'items')]
    private JobImageCopy $job;

    public function __construct()
    {
        $this->setStatus(AssetFileCopyStatus::Default);
        $this->setJob(new JobImageCopy());
    }

    public function getSourceAssetId(): string
    {
        return $this->sourceAssetId;
    }

    public function setSourceAssetId(string $sourceAssetId): self
    {
        $this->sourceAssetId = $sourceAssetId;

        return $this;
    }

    public function getTargetAssetId(): string
    {
        return $this->targetAssetId;
    }

    public function setTargetAssetId(string $targetAssetId): self
    {
        $this->targetAssetId = $targetAssetId;

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
