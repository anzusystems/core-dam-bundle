<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\Embeds\AudioAttributesAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\Embeds\AudioPublicLinkAdmDto;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AudioLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AudioFileAdmDetailDto extends AudioFileAdmListDto
{
    #[Serialize]
    protected AudioAttributesAdmDto $audioAttributes;

    #[Serialize]
    protected AudioPublicLinkAdmDto $publicLink;

    public static function getInstance(AudioFile $audioFile): static
    {
        return parent::getInstance($audioFile)
            ->setAudioAttributes(AudioAttributesAdmDto::getInstance($audioFile->getAttributes()))
            ->setPublicLink(AudioPublicLinkAdmDto::getInstance($audioFile->getAudioPublicLink()))
        ;
    }

    public function getAudioAttributes(): AudioAttributesAdmDto
    {
        return $this->audioAttributes;
    }

    public function setAudioAttributes(AudioAttributesAdmDto $audioAttributes): self
    {
        $this->audioAttributes = $audioAttributes;

        return $this;
    }

    public function getPublicLink(): AudioPublicLinkAdmDto
    {
        return $this->publicLink;
    }

    public function setPublicLink(AudioPublicLinkAdmDto $publicLink): self
    {
        $this->publicLink = $publicLink;

        return $this;
    }

    #[Serialize(handler: AudioLinksHandler::class)]
    public function getLinks(): AudioFile
    {
        dump($this->audio);
        return $this->audio;
    }

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->audio->getAssetAttributes()->getOriginAssetId();
    }
}
