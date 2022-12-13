<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Asset\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetTexts;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetTextsAdmListDto
{
    protected string $displayTitle;

    public static function getInstance(AssetTexts $assetTexts): static
    {
        return (new static())
            ->setDisplayTitle($assetTexts->getDisplayTitle());
    }

    #[Serialize]
    public function getDisplayTitle(): string
    {
        return $this->displayTitle;
    }

    public function setDisplayTitle(string $displayTitle): self
    {
        $this->displayTitle = $displayTitle;

        return $this;
    }
}
