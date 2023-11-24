<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AbstractAssetFileAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class VideoFileAdmListDto extends AbstractAssetFileAdmDto
{
    protected string $resourceName = VideoFile::class;

    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    protected VideoFile $video;

    public static function getInstance(VideoFile $videoFile): static
    {
        /** @psalm-var VideoFileAdmListDto $parent */
        $parent = parent::getAssetFileBaseInstance($videoFile);

        return $parent
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

    #[Serialize(handler: ImageLinksHandler::class, type: ImageLinksHandler::LIST_LINKS_TAGS)]
    public function getLinks(): ?AssetFile
    {
        return $this->video->getImagePreview()?->getImageFile();
    }
}
