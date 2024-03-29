<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class AudioAdmCreateDto extends AssetFileAdmCreateDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Choice(choices: AudioMimeTypes::CHOICES, message: ValidationException::ERROR_FIELD_INVALID)]
    protected string $mimeType;

    public function getAssetType(): AssetType
    {
        return AssetType::Audio;
    }
}
