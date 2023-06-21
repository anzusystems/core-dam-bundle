<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use RuntimeException;
use Throwable;

/**
 * @template-extends AbstractAssetFileFacade<VideoFile>
 */
final class VideoFacade extends AbstractAssetFileFacade
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
        $this->validator->validate($newVideo, $video);

        try {
            $this->videoManager->beginTransaction();
            $this->videoManager->update($video, $newVideo);
            $this->indexManager->index($video->getAsset());
            $this->videoManager->commit();
        } catch (Throwable $exception) {
            $this->assetManager->rollback();

            throw new RuntimeException('video_update_failed', 0, $exception);
        }

        return $video;
    }

    protected function getManager(): AssetFileManager
    {
        return $this->videoManager;
    }

    protected function getFactory(): AbstractAssetFileFactory
    {
        return $this->videoFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->videoRepository;
    }
}
