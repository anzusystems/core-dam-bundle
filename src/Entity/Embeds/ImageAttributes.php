<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Doctrine\Type\ColorType;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Color;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class ImageAttributes
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

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $animated;

    #[ORM\Column(type: ColorType::NAME, length: 255)]
    private Color $mostDominantColor;

    public function __construct()
    {
        $this->setRatioWidth(0);
        $this->setRatioHeight(0);
        $this->setWidth(0);
        $this->setHeight(0);
        $this->setRotation(0);
        $this->setMostDominantColor(new Color());
        $this->setAnimated(false);
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

    public function getMostDominantColor(): Color
    {
        return $this->mostDominantColor;
    }

    public function setMostDominantColor(Color $mostDominantColor): self
    {
        $this->mostDominantColor = $mostDominantColor;

        return $this;
    }

    public function isAnimated(): bool
    {
        return $this->animated;
    }

    public function setAnimated(bool $animated): self
    {
        $this->animated = $animated;

        return $this;
    }
}
