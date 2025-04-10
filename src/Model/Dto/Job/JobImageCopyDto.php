<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Job;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\AssetCopyEqualExtSystem]
final class JobImageCopyDto
{
    #[Serialize(handler: EntityIdHandler::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[BaseAppAssert\NotEmptyId]
    private AssetLicence $targetAssetLicence;

    #[Assert\Valid]
    #[Serialize(type: JobImageCopyItemDto::class)]
    #[NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Count(
        min: 1,
        max: 200,
        minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN,
        maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX
    )]
    private Collection $items;

    public function __construct()
    {
        $this->setTargetAssetLicence(new AssetLicence());
        $this->setItems(new ArrayCollection());
    }

    public function getTargetAssetLicence(): AssetLicence
    {
        return $this->targetAssetLicence;
    }

    public function setTargetAssetLicence(AssetLicence $targetAssetLicence): void
    {
        $this->targetAssetLicence = $targetAssetLicence;
    }

    /**
     * @return Collection<int, JobImageCopyItemDto>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setItems(Collection $items): void
    {
        $this->items = $items;
    }
}
