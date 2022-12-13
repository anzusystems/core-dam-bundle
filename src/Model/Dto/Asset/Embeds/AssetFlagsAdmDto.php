<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFlags;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFlagsAdmDto
{
    #[Serialize]
    private bool $described;

    #[Serialize]
    private bool $visible;

    public static function getInstance(AssetFlags $assetFlags): self
    {
        return (new self())
            ->setDescribed($assetFlags->isDescribed())
            ->setVisible($assetFlags->isVisible());
    }

    public function isDescribed(): bool
    {
        return $this->described;
    }

    public function setDescribed(bool $described): self
    {
        $this->described = $described;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }
}
