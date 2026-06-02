<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetFlags
{
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $described;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $visible;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $generatedBySystem;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $autocompletedMetadata;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $autoDeleteUnprocessed;

    /**
     * Asset holds TTS-generated audio. Auto-set when the asset is created from a TTS provider
     * (ElevenLabs, ...), otherwise manually managed via the asset sidebar.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $ttsAudio;

    public function __construct()
    {
        $this->setVisible(true);
        $this->setDescribed(false);
        $this->setAutoDeleteUnprocessed(true);
        $this->setAutocompletedMetadata(false);
        $this->setGeneratedBySystem(false);
        $this->setTtsAudio(false);
    }

    public function isGeneratedBySystem(): bool
    {
        return $this->generatedBySystem;
    }

    public function setGeneratedBySystem(bool $generatedBySystem): self
    {
        $this->generatedBySystem = $generatedBySystem;

        return $this;
    }

    public function isAutoDeleteUnprocessed(): bool
    {
        return $this->autoDeleteUnprocessed;
    }

    public function setAutoDeleteUnprocessed(bool $autoDeleteUnprocessed): self
    {
        $this->autoDeleteUnprocessed = $autoDeleteUnprocessed;

        return $this;
    }

    public function isDescribed(): bool
    {
        return $this->described;
    }

    public function isNotDescribed(): bool
    {
        return false === $this->described;
    }

    public function setDescribed(bool $described): self
    {
        $this->described = $described;

        return $this;
    }

    /**
     * @deprecated
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @deprecated
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function isAutocompletedMetadata(): bool
    {
        return $this->autocompletedMetadata;
    }

    public function isNotAutocompletedMetadata(): bool
    {
        return false === $this->autocompletedMetadata;
    }

    public function setAutocompletedMetadata(bool $autocompletedMetadata): self
    {
        $this->autocompletedMetadata = $autocompletedMetadata;

        return $this;
    }

    public function isTtsAudio(): bool
    {
        return $this->ttsAudio;
    }

    public function setTtsAudio(bool $ttsAudio): self
    {
        $this->ttsAudio = $ttsAudio;

        return $this;
    }
}
