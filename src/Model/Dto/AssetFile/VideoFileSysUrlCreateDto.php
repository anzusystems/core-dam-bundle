<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\VideoFileSysUrlCreate]
final class VideoFileSysUrlCreateDto extends AbstractAssetFileSysUrlCreateDto
{
    #[Assert\Unique(message: ValidationException::ERROR_FIELD_UNIQUE)]
    #[Serialize]
    private array $distributionServices = [];

    #[Serialize]
    private ?string $imagePreviewAssetFileId = null;

    public function getAssetType(): AssetType
    {
        return AssetType::Video;
    }

    public function getDistributionServices(): array
    {
        return $this->distributionServices;
    }

    public function setDistributionServices(array $distributionServices): self
    {
        $this->distributionServices = $distributionServices;

        return $this;
    }

    public function getImagePreviewAssetFileId(): ?string
    {
        return $this->imagePreviewAssetFileId;
    }

    public function setImagePreviewAssetFileId(?string $imagePreviewAssetFileId): self
    {
        $this->imagePreviewAssetFileId = $imagePreviewAssetFileId;

        return $this;
    }
}
