<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class VideoAttributes
{
    #[ORM\Column(type: Types::INTEGER)]
    private int $ratioWidth;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ratioHeight;

    #[ORM\Column(type: Types::INTEGER)]
    private int $width;

    #[ORM\Column(type: Types::INTEGER)]
    private int $height;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rotation;

    #[ORM\Column(type: Types::INTEGER)]
    private int $duration;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $codecName;

    #[ORM\Column(type: Types::INTEGER)]
    private int $bitrate;

    public function __construct()
    {
        $this->setRatioWidth(0);
        $this->setRatioHeight(0);
        $this->setWidth(0);
        $this->setHeight(0);
        $this->setRotation(0);
        $this->setBitrate(0);
        $this->setDuration(0);
        $this->setCodecName('');
    }

    public function getRatioWidth(): int
    {
        return $this->ratioWidth;
    }

    public function setRatioWidth(int $ratioWidth): self
    {
        $this->ratioWidth = $ratioWidth;

        return $this;
    }

    public function getRatioHeight(): int
    {
        return $this->ratioHeight;
    }

    public function setRatioHeight(int $ratioHeight): self
    {
        $this->ratioHeight = $ratioHeight;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getRotation(): int
    {
        return $this->rotation;
    }

    public function setRotation(int $rotation): self
    {
        $this->rotation = $rotation;

        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getCodecName(): string
    {
        return $this->codecName;
    }

    public function setCodecName(string $codecName): self
    {
        $this->codecName = $codecName;

        return $this;
    }

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
