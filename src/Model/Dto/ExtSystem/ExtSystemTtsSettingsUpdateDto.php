<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\ExtSystem;

use AnzuSystems\CoreDamBundle\Model\Enum\TtsActiveProviderMode;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class ExtSystemTtsSettingsUpdateDto
{
    #[Serialize]
    #[Assert\Uuid]
    private ?string $autoPodcastId = null;

    #[Serialize]
    #[Assert\Uuid]
    private ?string $recommendedPodcastId = null;

    #[Serialize]
    #[Assert\Uuid]
    private ?string $defaultVoiceFamilyId = null;

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

    public function getDefaultVoiceFamilyId(): ?string
    {
        return $this->defaultVoiceFamilyId;
    }

    public function setDefaultVoiceFamilyId(?string $defaultVoiceFamilyId): self
    {
        $this->defaultVoiceFamilyId = $defaultVoiceFamilyId;

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
