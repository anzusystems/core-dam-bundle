<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Video\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoAttributes;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class VideoAttributesAdmDto
{
    private int $width;
    private int $height;
    private int $ratioWidth;
    private int $ratioHeight;
    private int $rotation;
    private int $duration;
    private string $codecName;
    private int $bitrate;

    public static function getInstance(VideoAttributes $attributes): self
    {
        return (new self())
            ->setWidth($attributes->getWidth())
            ->setHeight($attributes->getHeight())
            ->setRatioHeight($attributes->getRatioHeight())
            ->setRatioWidth($attributes->getRatioWidth())
            ->setRotation($attributes->getRotation())
            ->setDuration($attributes->getDuration())
            ->setCodecName($attributes->getCodecName())
            ->setBitrate($attributes->getBitrate())
        ;
    }

    #[Serialize]
    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    #[Serialize]
    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    #[Serialize]
    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    #[Serialize]
    public function getRotation(): int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): self
    {
        $this->rotation = $rotation;

        return $this;
    }

    #[Serialize]
    public function getRatioWidth(): int
    {
        return $this->ratioWidth;
    }

    public function setRatioWidth(int $ratioWidth): self
    {
        $this->ratioWidth = $ratioWidth;

        return $this;
    }

    #[Serialize]
    public function getRatioHeight(): int
    {
        return $this->ratioHeight;
    }

    public function setRatioHeight(int $ratioHeight): self
    {
        $this->ratioHeight = $ratioHeight;

        return $this;
    }

    #[Serialize]
    public function getCodecName(): string
    {
        return $this->codecName;
    }

    public function setCodecName(string $codecName): self
    {
        $this->codecName = $codecName;

        return $this;
    }

    #[Serialize]
    public function getBitrate(): int
    {
        return $this->bitrate;
    }

    public function setBitrate(int $bitrate): self
    {
        $this->bitrate = $bitrate;

        return $this;
    }
}
