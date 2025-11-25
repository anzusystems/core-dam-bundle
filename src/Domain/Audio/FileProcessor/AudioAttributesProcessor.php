<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use InvalidArgumentException;

final class AudioAttributesProcessor
{
    public function __construct(
        private readonly FfmpegService $ffmpegService
    ) {
    }

    /**
     * @throws FfmpegException
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        if (false === ($assetFile instanceof AudioFile)) {
            throw new InvalidArgumentException('Asset type must be a type of audio');
        }

        $this->ffmpegService->populateAudioParams($assetFile, $file);

        return $assetFile;
    }
}
