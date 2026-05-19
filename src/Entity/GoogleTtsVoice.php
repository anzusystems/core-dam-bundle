<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CoreDamBundle\Model\Enum\GoogleSsmlGender;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\GoogleTtsVoiceRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoogleTtsVoiceRepository::class)]
#[ORM\Table(name: 'voice_google_tts')]
final class GoogleTtsVoice extends Voice
{
    public const float SPEAKING_RATE_DEFAULT = 1.0;
    public const float PITCH_DEFAULT = 0.0;

    #[ORM\Column(length: 32, enumType: GoogleSsmlGender::class)]
    #[Serialize]
    private GoogleSsmlGender $ssmlGender = GoogleSsmlGender::Neutral;

    #[ORM\Column(type: Types::FLOAT)]
    #[Serialize]
    #[Assert\Range(min: 0.25, max: 4.0)]
    private float $speakingRate = self::SPEAKING_RATE_DEFAULT;

    #[ORM\Column(type: Types::FLOAT)]
    #[Serialize]
    #[Assert\Range(min: -20.0, max: 20.0)]
    private float $pitch = self::PITCH_DEFAULT;

    #[Serialize]
    public function getDiscriminator(): VoiceDiscriminator
    {
        return VoiceDiscriminator::GoogleTts;
    }

    public function getSsmlGender(): GoogleSsmlGender
    {
        return $this->ssmlGender;
    }

    public function setSsmlGender(GoogleSsmlGender $ssmlGender): self
    {
        $this->ssmlGender = $ssmlGender;

        return $this;
    }

    public function getSpeakingRate(): float
    {
        return $this->speakingRate;
    }

    public function setSpeakingRate(float $speakingRate): self
    {
        $this->speakingRate = $speakingRate;

        return $this;
    }

    public function getPitch(): float
    {
        return $this->pitch;
    }

    public function setPitch(float $pitch): self
    {
        $this->pitch = $pitch;

        return $this;
    }
}
