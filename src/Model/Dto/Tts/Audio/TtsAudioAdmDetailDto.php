<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * Detail response for GET /api/adm/v1/tts-audio/{assetId}.
 */
final class TtsAudioAdmDetailDto
{
    #[Serialize]
    private string $assetId = App::EMPTY_STRING;

    #[Serialize]
    private ?TtsAsset $tts = null;

    public static function getInstance(Asset $asset, ?TtsAsset $tts): self
    {
        return (new self())
            ->setAssetId((string) $asset->getId())
            ->setTts($tts)
        ;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getTts(): ?TtsAsset
    {
        return $this->tts;
    }

    public function setTts(?TtsAsset $tts): self
    {
        $this->tts = $tts;

        return $this;
    }
}
