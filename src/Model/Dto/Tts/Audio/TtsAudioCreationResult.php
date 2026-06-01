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
     * @param AdapterFile $masterTmpFile The master MP3 already mirrored into the tmp filesystem during
     *     creation — downstream consumers (e.g. {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\PreviewMedia})
     *     can reuse it instead of re-downloading from remote storage.
     * @param AssetFileRoute $masterRoute Pre-built (persisted, not yet flushed) public route for the master
     *     audio. The orchestrator publishes it via {@see \AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade::makePublic()}
     *     after the bytes land in storage.
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
