<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Distribution;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

abstract class AbstractDistributionUpdateDto
{
    #[Serialize(serializedName: '_resourceName')]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected string $resourceName = '';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected string $distributionService = '';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected string $extId = '';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    protected DistributionProcessStatus $status = DistributionProcessStatus::Default;

    public function getDistributionService(): string
    {
        return $this->distributionService;
    }

    public function getResourceName(): string
    {
        return $this->resourceName;
    }

    public function setResourceName(string $resourceName): self
    {
        $this->resourceName = $resourceName;
        return $this;
    }

    public function setDistributionService(string $distributionService): self
    {
        $this->distributionService = $distributionService;
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
}
