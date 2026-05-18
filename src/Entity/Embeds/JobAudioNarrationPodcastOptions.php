<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Post-creation podcast attachment hints for the resulting TTS asset.
 */
#[ORM\Embeddable]
class JobAudioNarrationPodcastOptions
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $autoPodcastId;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $recommendedPodcastId;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $includeInRecommended;

    public function __construct()
    {
        $this->setAutoPodcastId(null);
        $this->setRecommendedPodcastId(null);
        $this->setIncludeInRecommended(false);
    }

    public function getAutoPodcastId(): ?string
    {
        return $this->autoPodcastId;
    }

    public function setAutoPodcastId(?string $autoPodcastId): self
    {
        $this->autoPodcastId = $autoPodcastId;

        return $this;
    }

    public function getRecommendedPodcastId(): ?string
    {
        return $this->recommendedPodcastId;
    }

    public function setRecommendedPodcastId(?string $recommendedPodcastId): self
    {
        $this->recommendedPodcastId = $recommendedPodcastId;

        return $this;
    }

    public function isIncludeInRecommended(): bool
    {
        return $this->includeInRecommended;
    }

    public function setIncludeInRecommended(bool $includeInRecommended): self
    {
        $this->includeInRecommended = $includeInRecommended;

        return $this;
    }
}
