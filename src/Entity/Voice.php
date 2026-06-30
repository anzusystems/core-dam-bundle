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
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\VoiceRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VoiceRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discriminator', enumType: VoiceDiscriminator::class)]
#[ORM\DiscriminatorMap(VoiceDiscriminator::MAP)]
#[ORM\UniqueConstraint(name: 'UNIQ_voice_family_discriminator', columns: ['voice_family_id', 'discriminator'])]
#[ORM\Index(name: 'IDX_voice_family', fields: ['voiceFamily'])]
abstract class Voice implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface, ExtSystemInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[ORM\ManyToOne(targetEntity: VoiceFamily::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    protected VoiceFamily $voiceFamily;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    protected string $externalVoiceId;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    protected bool $main;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    protected bool $active;

    public function __construct()
    {
        $this->setExternalVoiceId(App::EMPTY_STRING);
        $this->setMain(false);
        $this->setActive(true);
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
    }

    abstract public function getDiscriminator(): VoiceDiscriminator;

    public function getVoiceFamily(): VoiceFamily
    {
        return $this->voiceFamily;
    }

    public function setVoiceFamily(VoiceFamily $voiceFamily): static
    {
        $this->voiceFamily = $voiceFamily;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->voiceFamily->getExtSystem();
    }

    public function getExternalVoiceId(): string
    {
        return $this->externalVoiceId;
    }

    public function setExternalVoiceId(string $externalVoiceId): static
    {
        $this->externalVoiceId = $externalVoiceId;

        return $this;
    }

    /**
     * Whether this voice's model supports request stitching (previous_request_ids).
     * Default false; ElevenlabsVoice overrides per model. Google is stateless and never stitches,
     * so callers can skip fetching chain request-ids entirely for voices that return false.
     */
    public function supportsRequestStitching(): bool
    {
        return false;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    public function setMain(bool $main): static
    {
        $this->main = $main;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
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
