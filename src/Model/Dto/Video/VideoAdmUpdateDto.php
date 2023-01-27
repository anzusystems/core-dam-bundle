<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImagePreview;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ImagePreviewableInterface;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class VideoAdmUpdateDto extends AbstractEntityDto implements AssetLicenceInterface, ImagePreviewableInterface
{
    protected string $resourceName = VideoFile::class;

    #[Serialize]
    #[Assert\Valid]
    #[AppAssert\EqualLicence]
    private ?ImagePreview $imagePreview = null;
    private VideoFile $videoFile;

    public static function getInstance(VideoFile $videoFile): static
    {
        return parent::getBaseInstance($videoFile)
            ->setVideoFile($videoFile)
            ->setImagePreview($videoFile->getImagePreview())
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

    public function getLicence(): AssetLicence
    {
        return $this->getVideoFile()->getLicence();
    }

    public function getImagePreview(): ?ImagePreview
    {
        return $this->imagePreview;
    }

    public function setImagePreview(?ImagePreview $imagePreview): self
    {
        $this->imagePreview = $imagePreview;

        return $this;
    }
}
