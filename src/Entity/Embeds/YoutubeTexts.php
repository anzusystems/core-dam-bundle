<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class YoutubeTexts
{
    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 100, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Serialize]
    private string $title;

    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 5_000, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[ORM\Column(type: Types::STRING, length: 5_000)]
    #[Serialize]
    private string $description;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $keywords;

    public function __construct()
    {
        $this->setDescription('');
        $this->setTitle('');
        $this->setKeywords([]);
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

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }
}
