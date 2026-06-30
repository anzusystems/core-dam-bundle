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

    /**
     * ElevenLabs models that support request stitching (previous_request_ids / next_request_ids).
     * Allowlist by design: a model NOT listed (e.g. eleven_v3, a future eleven_v4) is synthesized
     * WITHOUT stitching instead of failing with HTTP 400 — so a new or incompatible model never breaks
     * synthesis, it only loses cross-chunk prosody continuity. Add a model here once it's confirmed to
     * support stitching.
     *
     * @var list<string>
     */
    public const array MODELS_WITH_REQUEST_STITCHING = [
        'eleven_multilingual_v2',
        'eleven_multilingual_v1',
        'eleven_monolingual_v1',
        'eleven_turbo_v2',
        'eleven_turbo_v2_5',
        'eleven_flash_v2',
        'eleven_flash_v2_5',
        'eleven_english_sts_v2',
    ];

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

    public function supportsRequestStitching(): bool
    {
        return in_array($this->modelId, self::MODELS_WITH_REQUEST_STITCHING, true);
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
