<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

final readonly class TtsAudioCreationResult
{
    /**
     * @param AdapterFile $masterTmpFile Master MP3 mirrored into tmp fs; reusable by downstream pipeline steps.
     * @param AssetFileRoute $masterRoute Persisted (not flushed) public route; orchestrator publishes after storage write.
     */
    public function __construct(
        public Asset $asset,
        public AudioFile $masterAudio,
        public TtsAsset $ttsAsset,
        public AdapterFile $masterTmpFile,
        public AssetFileRoute $masterRoute,
    ) {
    }
}
