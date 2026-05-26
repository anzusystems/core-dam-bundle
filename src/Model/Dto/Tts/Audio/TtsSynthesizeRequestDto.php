<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GroupSequence ensures {@see GROUP_POST} constraints (which dereference {@see getExtSystem()})
 * run only after the base group confirmed extSystem is set — otherwise the getter throws.
 */
#[Assert\Expression(
    '(this.getExtResourceName() === null) === (this.getExtId() === null)',
    message: 'fields.tts.extRef.must_be_both_null_or_both_present',
)]
#[Assert\GroupSequence(['TtsSynthesizeRequestDto', self::GROUP_POST])]
#[AppAssert\TtsLicenceResolvable(groups: [self::GROUP_POST])]
final class TtsSynthesizeRequestDto implements ExtSystemInterface
{
    private const string GROUP_POST = 'post';

    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(min: 10, max: 50_000, minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $text = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Length(max: 120, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $voiceFamilySlug = null;

    /**
     * @var Collection<int, Podcast>
     */
    #[Serialize(handler: EntityIdHandler::class, type: Podcast::class)]
    #[Assert\Valid]
    private Collection $podcasts;

    #[Serialize]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $extResourceName = null;

    #[Serialize]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $extId = null;

    #[Serialize]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $title = null;

    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ?ExtSystem $extSystem = null;

    #[Serialize(handler: EntityIdHandler::class)]
    #[AppAssert\EqualExtSystem(groups: [self::GROUP_POST])]
    private ?AssetLicence $assetLicence = null;

    public function __construct()
    {
        $this->setPodcasts(new ArrayCollection());
    }

    public function resolveAssetLicence(): AssetLicence
    {
        return $this->assetLicence ?? $this->extSystem?->getTtsDefaultAssetLicence() ?? throw new LogicException(
            'AssetLicence accessed before TtsSynthesizeRequestDto validation resolved it.',
        );
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getVoiceFamilySlug(): ?string
    {
        return $this->voiceFamilySlug;
    }

    public function setVoiceFamilySlug(?string $voiceFamilySlug): self
    {
        $this->voiceFamilySlug = $voiceFamilySlug;

        return $this;
    }

    /**
     * @return Collection<int, Podcast>
     */
    public function getPodcasts(): Collection
    {
        return $this->podcasts;
    }

    /**
     * @param Collection<int, Podcast> $podcasts
     */
    public function setPodcasts(Collection $podcasts): self
    {
        $this->podcasts = $podcasts;

        return $this;
    }

    public function getExtResourceName(): ?string
    {
        return $this->extResourceName;
    }

    public function setExtResourceName(?string $extResourceName): self
    {
        $this->extResourceName = $extResourceName;

        return $this;
    }

    public function getExtId(): ?string
    {
        return $this->extId;
    }

    public function setExtId(?string $extId): self
    {
        $this->extId = $extId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        return $this->extSystem ?? throw new LogicException(
            'ExtSystem accessed before TtsSynthesizeRequestDto validation resolved it.',
        );
    }

    public function setExtSystem(?ExtSystem $extSystem): self
    {
        $this->extSystem = $extSystem;

        return $this;
    }

    public function getAssetLicence(): ?AssetLicence
    {
        return $this->assetLicence;
    }

    public function setAssetLicence(?AssetLicence $assetLicence): self
    {
        $this->assetLicence = $assetLicence;

        return $this;
    }
}
