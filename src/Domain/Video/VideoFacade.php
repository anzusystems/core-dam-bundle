<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;

/**
 * @template-extends AssetFileFacade<VideoFile>
 */
final class VideoFacade extends AssetFileFacade
{
    public function __construct(
        private readonly VideoManager $videoManager,
        private readonly VideoFactory $videoFactory,
        private readonly VideoFileRepository $videoRepository,
    ) {
    }

    protected function getManager(): AssetFileManager
    {
        return $this->videoManager;
    }

    protected function getFactory(): AssetFileFactory
    {
        return $this->videoFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->videoRepository;
    }
}
