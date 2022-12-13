<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Decorator;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtSystemImageTypeAdmGetDecorator extends ExtSystemAssetTypeAdmGetDecorator
{
    private int $roiWidth;
    private int $roiHeight;

    #[Serialize]
    public function getRoiWidth(): int
    {
        return $this->roiWidth;
    }

    public function setRoiWidth(int $roiWidth): self
    {
        $this->roiWidth = $roiWidth;

        return $this;
    }

    #[Serialize]
    public function getRoiHeight(): int
    {
        return $this->roiHeight;
    }

    public function setRoiHeight(int $roiHeight): self
    {
        $this->roiHeight = $roiHeight;

        return $this;
    }
}
