<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\Embeds\AssetFileAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageCropTag;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AudioLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

class AudioFileAdmListDto extends AbstractEntityDto
{
    protected string $resourceName = AudioFile::class;
    protected AudioFile $audio;

    #[Serialize]
    protected AssetFileAttributesAdmDto $fileAttributes;

    public static function getInstance(AudioFile $audioFile): static
    {
        return parent::getBaseInstance($audioFile)
            ->setFileAttributes(AssetFileAttributesAdmDto::getInstance($audioFile->getAssetAttributes()))
            ->setAudio($audioFile);
    }

    public function getFileAttributes(): AssetFileAttributesAdmDto
    {
        return $this->fileAttributes;
    }

    public function setFileAttributes(AssetFileAttributesAdmDto $fileAttributes): self
    {
        $this->fileAttributes = $fileAttributes;

        return $this;
    }

    public function getAudio(): AudioFile
    {
        return $this->audio;
    }

    public function setAudio(AudioFile $audio): self
    {
        $this->audio = $audio;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class)]
    public function getAsset(): Asset
    {
        return $this->audio->getAsset();
    }

    #[Serialize(handler: AudioLinksHandler::class, type: ImageCropTag::LIST)]
    public function getLinks(): AudioFile
    {
        return $this->audio;

    }
}
