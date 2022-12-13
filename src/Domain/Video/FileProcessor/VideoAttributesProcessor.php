<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\FfmpegException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

final class VideoAttributesProcessor
{
    public function __construct(
        private readonly FfmpegService $ffmpegService
    ) {
    }

    /**
     * @param VideoFile $assetFile
     *
     * @throws FfmpegException
     */
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        $this->ffmpegService->populateVideoParams($assetFile, $file);

        return $assetFile;
    }
}
