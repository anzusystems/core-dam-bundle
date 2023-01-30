<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionFailReason;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\CustomData]
final class CustomDistributionAdmDto extends AbstractEntityDto implements ResourceCustomFormProvidableInterface, CustomDataInterface
{
    protected string $assetFileId;
    protected string $assetId;
    protected string $extId;
    protected DistributionProcessStatus $status;
    protected DistributionFailReason $failReason;
    protected array $distributionData;
    protected Collection $blocks;
    protected Collection $blockedBy;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $customData;

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $distributionService;

    public function __construct()
    {
        $this->setCustomData([]);
        $this->setDistributionService('');
        $this->setAssetId('');
        $this->setExtId('');
        $this->setStatus(DistributionProcessStatus::Default);
        $this->setFailReason(DistributionFailReason::None);
        $this->setDistributionData([]);
        $this->setBlocks(new ArrayCollection());
        $this->setBlockedBy(new ArrayCollection());
    }

    public static function getFromDistribution(Distribution $distribution): static
    {
        return self::getBaseInstance($distribution)
            ->setAssetFileId($distribution->getAssetFileId())
            ->setAssetId($distribution->getAssetId())
            ->setExtId($distribution->getExtId())
            ->setStatus($distribution->getStatus())
            ->setFailReason($distribution->getFailReason())
            ->setDistributionData($distribution->getDistributionData())
            ->setBlocks($distribution->getBlocks())
            ->setBlockedBy($distribution->getBlockedBy())
        ;
    }

    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function setBlocks(Collection $blocks): self
    {
        $this->blocks = $blocks;

        return $this;
    }

    public function getBlockedBy(): Collection
    {
        return $this->blockedBy;
    }

    public function setBlockedBy(Collection $blockedBy): self
    {
        $this->blockedBy = $blockedBy;

        return $this;
    }

    public function getAssetFileId(): string
    {
        return $this->assetFileId;
    }

    public function setAssetFileId(string $assetFileId): self
    {
        $this->assetFileId = $assetFileId;

        return $this;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getExtId(): string
    {
        return $this->extId;
    }

    public function setExtId(string $extId): self
    {
        $this->extId = $extId;

        return $this;
    }

    public function getStatus(): DistributionProcessStatus
    {
        return $this->status;
    }

    public function setStatus(DistributionProcessStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFailReason(): DistributionFailReason
    {
        return $this->failReason;
    }

    public function setFailReason(DistributionFailReason $failReason): self
    {
        $this->failReason = $failReason;

        return $this;
    }

    public function getDistributionData(): array
    {
        return $this->distributionData;
    }

    public function setDistributionData(array $distributionData): self
    {
        $this->distributionData = $distributionData;

        return $this;
    }

    public function getResourceKey(): string
    {
        return CustomFormFactory::getDistributionServiceResourceKey($this->getDistributionService());
    }

    public function getCustomData(): array
    {
        return $this->customData;
    }

    public function setCustomData(array $customData): static
    {
        $this->customData = $customData;

        return $this;
    }

    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function setDistributionService(string $distributionService): self
    {
        $this->distributionService = $distributionService;

        return $this;
    }
}
