<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

final class AudioAttributesProcessor
{
    public function __construct(
        private readonly FfmpegService $ffmpegService
    ) {
    }

    /**
     * @param AudioFile $assetFile
     */
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        $this->ffmpegService->populateAudioParams($assetFile, $file);

        return $assetFile;
    }
}
