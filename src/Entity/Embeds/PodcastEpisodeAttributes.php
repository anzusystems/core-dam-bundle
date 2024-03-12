<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\PodcastEpisodeStatus;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class PodcastEpisodeAttributes
{
    /**
     * Audio track URL provided by external service (obtained from RSS FEED)
     */
    #[ORM\Column(type: Types::STRING, length: 2_048)]
    private string $rssUrl;

    /**
     * Podcast episode URL (located in external service)
     */
    #[ORM\Column(type: Types::STRING, length: 2_048, options: ['default' => ''])]
    #[Assert\Length(max: 2_048, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\Url(message: ValidationException::ERROR_FIELD_INVALID)]
    #[Serialize]
    private string $extUrl;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $rssId;

    #[ORM\Column(enumType: PodcastEpisodeStatus::class)]
    private PodcastEpisodeStatus $lastImportStatus;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\Range(maxMessage: ValidationException::ERROR_FIELD_RANGE_MAX, max: 65_535)]
    #[Serialize]
    private ?int $seasonNumber;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\Range(maxMessage: ValidationException::ERROR_FIELD_RANGE_MAX, max: 65_535)]
    #[Serialize]
    private ?int $episodeNumber;

    public function __construct()
    {
        $this->setEpisodeNumber(null);
        $this->setSeasonNumber(null);
        $this->setLastImportStatus(PodcastEpisodeStatus::Default);
        $this->setRssId('');
        $this->setRssUrl('');
        $this->setExtUrl('');
    }

    #[Serialize]
    public function getLastImportStatus(): PodcastEpisodeStatus
    {
        return $this->lastImportStatus;
    }

    public function setLastImportStatus(PodcastEpisodeStatus $lastImportStatus): self
    {
        $this->lastImportStatus = $lastImportStatus;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(?int $seasonNumber): self
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(?int $episodeNumber): self
    {
        $this->episodeNumber = $episodeNumber;

        return $this;
    }

    #[Serialize]
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
    public function getRssId(): string
    {
        return $this->rssId;
    }

    public function setRssId(string $rssId): self
    {
        $this->rssId = $rssId;

        return $this;
    }

    public function getExtUrl(): string
    {
        return $this->extUrl;
    }

    public function setExtUrl(string $extUrl): self
    {
        $this->extUrl = $extUrl;

        return $this;
    }
}
