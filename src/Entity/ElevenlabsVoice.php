<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\ElevenlabsVoiceRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ElevenlabsVoiceRepository::class)]
#[ORM\Table(name: 'voice_elevenlabs')]
final class ElevenlabsVoice extends Voice
{
    public const string MODEL_DEFAULT = 'eleven_multilingual_v2';
    public const float STABILITY_DEFAULT = 0.5;
    public const float SIMILARITY_BOOST_DEFAULT = 0.75;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => self::MODEL_DEFAULT])]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $modelId = self::MODEL_DEFAULT;

    #[ORM\Column(type: Types::FLOAT)]
    #[Serialize]
    #[Assert\Range(min: 0.0, max: 1.0)]
    private float $stability = self::STABILITY_DEFAULT;

    #[ORM\Column(type: Types::FLOAT)]
    #[Serialize]
    #[Assert\Range(min: 0.0, max: 1.0)]
    private float $similarityBoost = self::SIMILARITY_BOOST_DEFAULT;

    #[Serialize]
    public function getDiscriminator(): VoiceDiscriminator
    {
        return VoiceDiscriminator::Elevenlabs;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function setModelId(string $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getStability(): float
    {
        return $this->stability;
    }

    public function setStability(float $stability): self
    {
        $this->stability = $stability;

        return $this;
    }

    public function getSimilarityBoost(): float
    {
        return $this->similarityBoost;
    }

    public function setSimilarityBoost(float $similarityBoost): self
    {
        $this->similarityBoost = $similarityBoost;

        return $this;
    }
}
