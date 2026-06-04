<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * ElevenLabs text-to-speech request body. Serialized by {@see ElevenlabsClient} with null-skipping, so
 * `previous_request_ids` is omitted when left null (an empty list would otherwise serialize to `[]`).
 */
final class ElevenlabsSynthesizeRequestDto
{
    #[Serialize]
    private string $text = '';

    #[Serialize(serializedName: 'model_id')]
    private string $modelId = '';

    #[Serialize(serializedName: 'voice_settings')]
    private ElevenlabsVoiceSettingsDto $voiceSettings;

    /**
     * Oldest-first, ≤3; omitted from the payload when null.
     *
     * @var list<string>|null
     */
    #[Serialize(serializedName: 'previous_request_ids')]
    private ?array $previousRequestIds = null;

    public function __construct()
    {
        $this->voiceSettings = new ElevenlabsVoiceSettingsDto();
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function setModelId(string $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getVoiceSettings(): ElevenlabsVoiceSettingsDto
    {
        return $this->voiceSettings;
    }

    /**
     * @return list<string>|null
     */
    public function getPreviousRequestIds(): ?array
    {
        return $this->previousRequestIds;
    }

    /**
     * @param list<string> $previousRequestIds
     */
    public function setPreviousRequestIds(array $previousRequestIds): self
    {
        $this->previousRequestIds = $previousRequestIds;

        return $this;
    }
}
