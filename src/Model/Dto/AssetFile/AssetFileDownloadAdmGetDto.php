<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AssetFileDownloadAdmGetDto extends AbstractEntityDto
{
    private string $link;

    public static function getInstance(
        AssetFile $assetFile,
        string $link,
    ): static {
        return parent::getBaseInstance($assetFile)
            ->setResourceName($assetFile::class)
            ->setLink($link)
        ;
    }

    #[Serialize]
    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function setResourceName(string $resourceName): static
    {
        $this->resourceName = $resourceName;

        return $this;
    }
}
