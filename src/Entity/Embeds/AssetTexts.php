<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetTexts
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $displayTitle;

    public function __construct()
    {
        $this->setDisplayTitle('');
    }

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
