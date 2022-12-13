<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetExternalProviderTextsDto
{
    protected string $displayTitle;
    protected string $description;

    public static function getInstance(string $displayTitle, string $description): static
    {
        return (new self())
            ->setDisplayTitle($displayTitle)
            ->setDescription($description)
        ;
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

    #[Serialize]
    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
