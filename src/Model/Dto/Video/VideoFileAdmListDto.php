<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\LinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class VideoFileAdmListDto extends AbstractAssetFileAdmDto
{
    protected string $resourceName = VideoFile::class;

    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    protected VideoFile $video;

    public static function getInstance(VideoFile $videoFile): static
    {
        return parent::getAssetFileBaseInstance($videoFile)
            ->setVideo($videoFile);
    }

    #[Serialize]
    public function getImagePreview(): ?ImagePreview
    {
        return $this->video->getImagePreview();
    }

    public function getVideo(): VideoFile
    {
        return $this->video;
    }

    public function setVideo(VideoFile $video): static
    {
        $this->video = $video;

        return $this;
    }

    #[Serialize(handler: LinksHandler::class)]
    public function getLinks(): ?AssetFile
    {
        return $this->video->getImagePreview()?->getImageFile();
    }
}
