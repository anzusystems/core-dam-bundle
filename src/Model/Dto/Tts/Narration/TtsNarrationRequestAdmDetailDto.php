<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Narration;

use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class TtsNarrationRequestAdmDetailDto
{
    #[Serialize]
    private TtsNarrationRequest $request;

    #[Serialize]
    private ?TtsAsset $ttsAsset;

    public static function getInstance(TtsNarrationRequest $request, ?TtsAsset $ttsAsset): self
    {
        return (new self())
            ->setRequest($request)
            ->setTtsAsset($ttsAsset)
        ;
    }

    public function getRequest(): TtsNarrationRequest
    {
        return $this->request;
    }

    public function setRequest(TtsNarrationRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getTtsAsset(): ?TtsAsset
    {
        return $this->ttsAsset;
    }

    public function setTtsAsset(?TtsAsset $ttsAsset): self
    {
        $this->ttsAsset = $ttsAsset;

        return $this;
    }
}
