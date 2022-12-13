<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video;

use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\Embeds\VideoAttributesAdmDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class VideoFileAdmDetailDto extends VideoFileAdmListDto
{
    #[Serialize]
    protected VideoAttributesAdmDto $videoAttributes;

    public static function getInstance(VideoFile $videoFile): static
    {
        return parent::getInstance($videoFile)
            ->setVideoAttributes(VideoAttributesAdmDto::getInstance($videoFile->getAttributes()));
    }

    public function getVideoAttributes(): VideoAttributesAdmDto
    {
        return $this->videoAttributes;
    }

    public function setVideoAttributes(VideoAttributesAdmDto $videoAttributes): self
    {
        $this->videoAttributes = $videoAttributes;

        return $this;
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->video->getAssetAttributes()->getOriginAssetId();
    }
}
