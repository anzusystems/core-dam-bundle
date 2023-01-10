<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

final class VideoAdmUpdateDto extends AbstractEntityDto implements AssetLicenceInterface
{
    protected string $resourceName = VideoFile::class;

    #[Serialize(handler: EntityIdHandler::class)]
    #[AppAssert\AssetProperties(assetType: AssetType::Image)]
    #[AppAssert\EqualLicence]
    private ?Asset $previewImage;
    private VideoFile $videoFile;

    public static function getInstance(VideoFile $videoFile): static
    {
        return parent::getBaseInstance($videoFile)
            ->setVideoFile($videoFile)
            ->setPreviewImage($videoFile->getPreviewImage())
        ;
    }

    public function getVideoFile(): VideoFile
    {
        return $this->videoFile;
    }

    public function setVideoFile(VideoFile $videoFile): self
    {
        $this->videoFile = $videoFile;

        return $this;
    }

    public function getPreviewImage(): ?Asset
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?Asset $previewImage): self
    {
        $this->previewImage = $previewImage;

        return $this;
    }

    public function getLicence(): AssetLicence
    {
        return $this->getVideoFile()->getLicence();
    }
}
