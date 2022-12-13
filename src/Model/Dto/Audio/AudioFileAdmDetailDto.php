<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio;

use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\Embeds\AudioAttributesAdmDto;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AudioFileAdmDetailDto extends AudioFileAdmListDto
{
    #[Serialize]
    protected AudioAttributesAdmDto $audioAttributes;

    public static function getInstance(AudioFile $audioFile): static
    {
        return parent::getInstance($audioFile)
            ->setAudioAttributes(AudioAttributesAdmDto::getInstance($audioFile->getAttributes()));
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

    #[Serialize]
    public function getOriginAssetFile(): string
    {
        return $this->audio->getAssetAttributes()->getOriginAssetId();
    }
}
