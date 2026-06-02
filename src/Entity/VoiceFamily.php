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
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VoiceFamilyRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_voice_family_ext_system_slug', fields: ['extSystem', 'slug'])]
#[ORM\Index(name: 'IDX_voice_family_ext_system', fields: ['extSystem'])]
final class VoiceFamily implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface, ExtSystemInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[ORM\ManyToOne(targetEntity: ExtSystem::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\JoinColumn(nullable: false)]
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ExtSystem $extSystem;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Regex(pattern: '/^[a-z0-9_-]+$/', message: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Length(max: 120, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $displayName;

    #[ORM\Column(type: Types::STRING, length: 16)]
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(max: 16, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $language;

    #[ORM\Column(enumType: VoiceDiscriminator::class, nullable: true)]
    #[Serialize]
    private ?VoiceDiscriminator $preferredProvider;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $active;

    /**
     * Keywords auto-applied to every TTS asset narrated with this family.
     *
     * @var Collection<int, Keyword>
     */
    #[ORM\ManyToMany(targetEntity: Keyword::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\JoinTable(name: 'voice_family_keyword')]
    #[Serialize(handler: EntityIdHandler::class, type: Keyword::class)]
    #[Assert\All([new AppAssert\EqualExtSystem()])]
    private Collection $keywords;

    #[ORM\OneToMany(targetEntity: Voice::class, mappedBy: 'voiceFamily', fetch: App::DOCTRINE_EXTRA_LAZY)]
    #[ORM\OrderBy(['externalVoiceId' => 'ASC'])]
    private Collection $voices;

    public function __construct()
    {
        $this->setSlug(App::EMPTY_STRING);
        $this->setDisplayName(App::EMPTY_STRING);
        $this->setLanguage(App::EMPTY_STRING);
        $this->setPreferredProvider(null);
        $this->setActive(true);
        $this->setKeywords(new ArrayCollection());
        $this->setVoices(new ArrayCollection());
        $this->setCreatedAt(App::getAppDate());
        $this->setModifiedAt(App::getAppDate());
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem;
    }

    public function setExtSystem(ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getPreferredProvider(): ?VoiceDiscriminator
    {
        return $this->preferredProvider;
    }

    public function setPreferredProvider(?VoiceDiscriminator $preferredProvider): self
    {
        $this->preferredProvider = $preferredProvider;

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

    /**
     * @return Collection<int, Keyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    /**
     * @param Collection<int, Keyword> $keywords
     */
    public function setKeywords(Collection $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function addKeyword(Keyword $keyword): self
    {
        if (false === $this->keywords->contains($keyword)) {
            $this->keywords->add($keyword);
        }

        return $this;
    }

    public function removeKeyword(Keyword $keyword): self
    {
        $this->keywords->removeElement($keyword);

        return $this;
    }

    /**
     * @return Collection<int, Voice>
     */
    public function getVoices(): Collection
    {
        return $this->voices;
    }

    public function setVoices(Collection $voices): self
    {
        $this->voices = $voices;

        return $this;
    }

    public static function getResourceName(): string
    {
        return 'voice_family';
    }

    public static function getSystem(): string
    {
        return 'dam';
    }
}
