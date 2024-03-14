<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\CustomDataInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

final class AssetFileSysUrlCreateDto extends AbstractAssetFileSysDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 2048, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $url = '';

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }
}
