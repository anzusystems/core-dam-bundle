<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\VideoMimeTypes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoAdmCreateDto extends AssetFileAdmCreateDto
{
    #[Serialize]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: VideoMimeTypes::CHOICES, message: ValidationException::ERROR_FIELD_INVALID)]
    protected string $mimeType;

    public function getAssetType(): AssetType
    {
        return AssetType::Video;
    }
}
