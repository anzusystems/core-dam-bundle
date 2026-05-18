<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

final readonly class TtsAudioCreationResult
{
    /**
     * @param AdapterFile $masterTmpFile The master MP3 already mirrored into the tmp filesystem during
     *     creation — downstream consumers (e.g. {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\PreviewMedia})
     *     can reuse it instead of re-downloading from remote storage.
     */
    public function __construct(
        public Asset $asset,
        public AudioFile $masterAudio,
        public TtsAsset $ttsAsset,
        public AdapterFile $masterTmpFile,
    ) {
    }
}
