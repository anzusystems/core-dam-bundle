<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmUpdateDto;

final class VideoManager extends AssetFileManager
{
    public function update(VideoFile $video, VideoAdmUpdateDto $newVideo, bool $flush = true): VideoFile
    {
        $this->trackModification($video);
        $video
            ->setPreviewImage($newVideo->getPreviewImage())
        ;
        $this->flush($flush);

        return $video;
    }
}
