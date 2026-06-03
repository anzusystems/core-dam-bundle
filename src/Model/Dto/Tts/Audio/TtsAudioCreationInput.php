<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

final readonly class TtsAudioCreationInput
{
    public function __construct(
        public AdapterFile $audioFile,
        public VoiceFamily $family,
        public Voice $voice,
        public AssetLicence $licence,
        public string $sourceTextHash,
        public string $sourceTextSnapshot,
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }

    public static function forInitialRequest(
        TtsNarrationRequest $request,
        AdapterFile $audioFile,
        VoiceFamily $family,
        Voice $voice,
        AssetLicence $licence,
        string $sourceText,
    ): self {
        return new self(
            audioFile: $audioFile,
            family: $family,
            voice: $voice,
            licence: $licence,
            sourceTextHash: hash('sha256', $sourceText),
            sourceTextSnapshot: $sourceText,
            title: $request->getTitle(),
            description: $request->getDescription(),
        );
    }

    /**
     * Regeneration re-synthesises the SAME source text (snapshot/hash carried over from the stable
     * {@see TtsAsset}) with a possibly different voice/family. The freshly built audio is attached to the
     * existing stable asset and the previous audio is kept for a grace period — see
     * {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\AssetSwap}.
     */
    public static function forRegenerate(
        TtsNarrationRequest $request,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        VoiceFamily $family,
        Voice $voice,
        AssetLicence $licence,
    ): self {
        return new self(
            audioFile: $audioFile,
            family: $family,
            voice: $voice,
            licence: $licence,
            sourceTextHash: $stableTts->getSourceTextHash(),
            sourceTextSnapshot: $stableTts->getSourceTextSnapshot(),
            title: $request->getTitle(),
        );
    }
}
