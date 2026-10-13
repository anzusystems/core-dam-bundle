<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class JwTexts
{
    public const int TITLE_LENGTH = 100;
    public const int DESCRIPTION_LENGTH = 5_000;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: self::TITLE_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Serialize]
    private string $title;

    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: self::DESCRIPTION_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[ORM\Column(type: Types::STRING, length: self::DESCRIPTION_LENGTH)]
    #[Serialize]
    private string $description;

    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 5_000, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $author;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $keywords;

    public function __construct()
    {
        $this->setDescription('');
        $this->setTitle('');
        $this->setKeywords([]);
        $this->setAuthor('');
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

    public function setNullAuthor(?string $author): self
    {
        $this->author = (string) $author;

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

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }
}
