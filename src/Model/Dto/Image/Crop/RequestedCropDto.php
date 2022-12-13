<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop;

final class RequestedCropDto
{
    private int $requestWidth;
    private int $requestHeight;
    private int $roi;
    private ?int $quality;

    public function __construct()
    {
        $this->setQuality(null);
        $this->setRoi(0);
        $this->setRequestWidth(0);
        $this->setRequestHeight(0);
    }

    public function getRequestWidth(): int
    {
        return $this->requestWidth;
    }

    public function setRequestWidth(int $requestWidth): self
    {
        $this->requestWidth = $requestWidth;

        return $this;
    }

    public function getRequestHeight(): int
    {
        return $this->requestHeight;
    }

    public function setRequestHeight(int $requestHeight): self
    {
        $this->requestHeight = $requestHeight;

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(?int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function getRoi(): int
    {
        return $this->roi;
    }

    public function setRoi(int $roi): self
    {
        $this->roi = $roi;

        return $this;
    }
}
