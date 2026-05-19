<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class ExtSystemTtsSettings
{
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $autoPodcastId = null;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $recommendedPodcastId = null;

    #[ORM\Column(enumType: TtsActiveProviderMode::class, options: ['default' => 'auto'])]
    #[Serialize]
    private TtsActiveProviderMode $activeProviderMode = TtsActiveProviderMode::Default;

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

    public function getActiveProviderMode(): TtsActiveProviderMode
    {
        return $this->activeProviderMode;
    }

    public function setActiveProviderMode(TtsActiveProviderMode $activeProviderMode): self
    {
        $this->activeProviderMode = $activeProviderMode;

        return $this;
    }
}
