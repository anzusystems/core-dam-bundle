<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastImportMode;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastLastImportStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class PodcastAttributes
{
    #[ORM\Column(type: Types::STRING, length: 2_048, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\Url(message: ValidationException::ERROR_FIELD_INVALID)]
    #[Serialize]
    private string $rssUrl;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    #[Assert\Length(max: 128, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $filePosition;

    #[ORM\Column(enumType: PodcastLastImportStatus::class)]
    private PodcastLastImportStatus $lastImportStatus;

    #[ORM\Column(enumType: PodcastImportMode::class)]
    #[Serialize]
    private PodcastImportMode $mode;

    public function __construct()
    {
        $this->setRssUrl('');
        $this->setFilePosition('');
        $this->setLastImportStatus(PodcastLastImportStatus::Default);
        $this->setMode(PodcastImportMode::Default);
    }

    public function getRssUrl(): string
    {
        return $this->rssUrl;
    }

    public function setRssUrl(string $rssUrl): self
    {
        $this->rssUrl = $rssUrl;

        return $this;
    }

    #[Serialize]
    public function getLastImportStatus(): PodcastLastImportStatus
    {
        return $this->lastImportStatus;
    }

    public function setLastImportStatus(PodcastLastImportStatus $lastImportStatus): self
    {
        $this->lastImportStatus = $lastImportStatus;

        return $this;
    }

    public function getMode(): PodcastImportMode
    {
        return $this->mode;
    }

    public function setMode(PodcastImportMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getFilePosition(): string
    {
        return $this->filePosition;
    }

    public function setFilePosition(string $filePosition): self
    {
        $this->filePosition = $filePosition;

        return $this;
    }
}
