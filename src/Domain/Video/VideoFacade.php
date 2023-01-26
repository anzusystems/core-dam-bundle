<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use RuntimeException;

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

    /**
     * @throws ValidationException
     */
    public function update(VideoFile $video, VideoAdmUpdateDto $newVideo): VideoFile
    {
        $this->entityValidator->validateDto($newVideo, $video);

        try {
            $this->videoManager->beginTransaction();
            $this->videoManager->update($video, $newVideo);
            $this->indexManager->index($video->getAsset());
            $this->videoManager->commit();
        } catch (\Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('video_update_failed', 0, $exception);
        }

        return $video;
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
