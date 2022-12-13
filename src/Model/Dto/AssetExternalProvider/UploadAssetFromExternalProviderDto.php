<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\UploadAssetFromExternalProvider]
final class UploadAssetFromExternalProviderDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $id = '';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    private string $externalProvider = '';
    private AssetLicence $assetLicence;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getExternalProvider(): string
    {
        return $this->externalProvider;
    }

    public function setExternalProvider(string $externalProvider): self
    {
        $this->externalProvider = $externalProvider;

        return $this;
    }

    public function getAssetLicence(): AssetLicence
    {
        return $this->assetLicence;
    }

    public function setAssetLicence(AssetLicence $assetLicence): self
    {
        $this->assetLicence = $assetLicence;

        return $this;
    }
}
