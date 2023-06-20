<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class PodcastEpisodeTexts
{
    public const TITLE_LENGTH = 255;
    public const DESCRIPTION_LENGTH = 5_000;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(max: self::TITLE_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(max: self::DESCRIPTION_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Serialize]
    private string $description;

    #[ORM\Column(type: Types::TEXT)]
    private string $rawDescription;

    public function __construct()
    {
        $this->setTitle('');
        $this->setDescription('');
        $this->setRawDescription('');
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    #[Serialize]
    public function getRawDescription(): string
    {
        return $this->rawDescription;
    }

    public function setRawDescription(string $rawDescription): self
    {
        $this->rawDescription = $rawDescription;

        return $this;
    }
}
