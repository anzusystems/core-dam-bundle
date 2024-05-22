<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class VideoShowEpisodeTexts
{
    public const int TITLE_LENGTH = 255;

    #[ORM\Column(type: Types::STRING, length: self::TITLE_LENGTH)]
    #[Assert\Length(max: self::TITLE_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize]
    private string $title;

    public function __construct()
    {
        $this->setTitle('');
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
