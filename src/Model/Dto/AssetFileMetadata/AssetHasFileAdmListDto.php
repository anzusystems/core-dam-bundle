<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFileMetadata;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetHasFile;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

class AssetHasFileAdmListDto
{
    protected string $resourceName = AssetHasFile::class;
    protected AssetHasFile $assetHasFile;

    public static function getInstance(AssetHasFile $assetHasFile): static
    {
        return (new self())
            ->setAssetHasFile($assetHasFile);
    }

    public function getAssetHasFile(): AssetHasFile
    {
        return $this->assetHasFile;
    }

    public function setAssetHasFile(AssetHasFile $assetHasFile): self
    {
        $this->assetHasFile = $assetHasFile;

        return $this;
    }

    #[Serialize(handler: AssetFileHandler::class, type: ImageCropTag::LIST)]
    public function getAssetFile(): AssetFile
    {
        return $this->assetHasFile->getAssetFile();
    }

    #[Serialize]
    public function getVersionTitle(): string
    {
        return $this->assetHasFile->getVersionTitle();
    }

    #[Serialize]
    public function isDefault(): bool
    {
        return $this->assetHasFile->isDefault();
    }

    #[Serialize]
    public function isMain(): bool
    {
        // todo implement main
        return $this->assetHasFile->isDefault();
    }
}
