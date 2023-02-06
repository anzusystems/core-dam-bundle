<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\ImageLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class VideoFileAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = VideoFile::class;
    protected VideoFile $video;

    #[Serialize]
    protected AssetFileAttributesAdmDto $fileAttributes;

    public static function getInstance(VideoFile $videoFile): static
    {
        return parent::getBaseInstance($videoFile)
            ->setFileAttributes(AssetFileAttributesAdmDto::getInstance($videoFile->getAssetAttributes()))
            ->setVideo($videoFile);
    }

    #[Serialize]
    public function getImagePreview(): ?ImagePreview
    {
        return $this->video->getImagePreview();
    }

    public function getFileAttributes(): AssetFileAttributesAdmDto
    {
        return $this->fileAttributes;
    }

    public function setFileAttributes(AssetFileAttributesAdmDto $fileAttributes): self
    {
        $this->fileAttributes = $fileAttributes;

        return $this;
    }

    public function getVideo(): VideoFile
    {
        return $this->video;
    }

    public function setVideo(VideoFile $video): self
    {
        $this->video = $video;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getAsset(): Asset
    {
        return $this->video->getAsset();
    }

    #[Serialize(handler: ImageLinksHandler::class, type: ImageCropTag::LIST)]
    public function getLinks(): ?AssetFile
    {
        return $this->video->getImagePreview()?->getImageFile();
    }
}
