<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
use AnzuSystems\CoreDamBundle\Repository\VoiceRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VoiceRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_voice_family_provider', fields: ['voiceFamily', 'provider'])]
#[ORM\Index(name: 'IDX_voice_family', fields: ['voiceFamily'])]
#[ORM\Index(name: 'IDX_provider', fields: ['provider'])]
final class Voice implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface, ExtSystemInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[ORM\ManyToOne(targetEntity: VoiceFamily::class, inversedBy: 'voices', fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private VoiceFamily $voiceFamily;

    #[ORM\Column(enumType: TtsProvider::class)]
    #[Serialize]
    #[Assert\NotNull(message: ValidationException::ERROR_FIELD_EMPTY)]
    private TtsProvider $provider;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $externalVoiceId;

    /**
     * Free-form provider-specific metadata (e.g. `['gender' => 'female', 'modelId' => 'eleven_multilingual_v2']`).
     * Must use KEYS_VALUES so the keys survive JSON round-trip (default strategy collapses to a sequential list).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $metadata;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $main;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $active;

    public function __construct()
    {
        $this->setExternalVoiceId(App::EMPTY_STRING);
        $this->setMetadata([]);
        $this->setMain(false);
        $this->setActive(true);
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
    }

    public function getVoiceFamily(): VoiceFamily
    {
        return $this->voiceFamily;
    }

    public function setVoiceFamily(VoiceFamily $voiceFamily): self
    {
        $this->voiceFamily = $voiceFamily;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->voiceFamily->getExtSystem();
    }

    public function getProvider(): TtsProvider
    {
        return $this->provider;
    }

    public function setProvider(TtsProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalVoiceId(): string
    {
        return $this->externalVoiceId;
    }

    public function setExternalVoiceId(string $externalVoiceId): self
    {
        $this->externalVoiceId = $externalVoiceId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    public function setMain(bool $main): self
    {
        $this->main = $main;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public static function getResourceName(): string
    {
        return 'voice';
    }

    public static function getSystem(): string
    {
        return 'dam';
    }
}
