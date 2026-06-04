<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ExtSystemInterface;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\TtsLicenceResolvable]
final class TtsSynthesizeRequestDto implements ExtSystemInterface, AssetLicenceInterface
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(min: 10, max: 50_000, minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $text = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Regex(pattern: VoiceFamily::SLUG_REGEX, message: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Length(max: VoiceFamily::SLUG_MAX_LENGTH, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $voiceFamilySlug = null;

    /**
     * @var Collection<int, Podcast>
     */
    #[Serialize(handler: EntityIdHandler::class, type: Podcast::class)]
    #[Assert\Valid]
    private Collection $podcasts;

    #[Serialize]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $title = null;

    #[Serialize]
    #[Assert\Length(max: 5_000, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $description = null;

    /**
     * @var string[]
     */
    #[Serialize]
    #[Assert\Count(max: 100, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\All([new Assert\Length(max: 255)])]
    private array $keywords = [];

    /**
     * @var string[]
     */
    #[Serialize]
    #[Assert\Count(max: 100, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\All([new Assert\Length(max: 255)])]
    private array $authors = [];

    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ?ExtSystem $extSystem = null;

    #[Serialize(handler: EntityIdHandler::class)]
    private ?AssetLicence $assetLicence = null;

    public function __construct()
    {
        $this->setPodcasts(new ArrayCollection());
    }

    public function resolveAssetLicence(): AssetLicence
    {
        // Guard: post-validation only — null = bug.
        return $this->assetLicence ?? $this->extSystem?->getTtsDefaultAssetLicence()
            ?? throw new LogicException('AssetLicence accessed before TtsSynthesizeRequestDto validation resolved it.');
    }

    public function getLicence(): AssetLicence
    {
        return $this->resolveAssetLicence();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param string[] $keywords
     */
    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAuthors(): array
    {
        return $this->authors;
    }

    /**
     * @param string[] $authors
     */
    public function setAuthors(array $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    public function getExtSystem(): ExtSystem
    {
        // Guard: post-validation only — null = bug.
        return $this->extSystem
            ?? throw new LogicException('ExtSystem accessed before TtsSynthesizeRequestDto validation resolved it.');
    }

    /**
     * Non-throwing accessor for validators that run before extSystem is confirmed present.
     */
    public function getExtSystemOrNull(): ?ExtSystem
    {
        return $this->extSystem;
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
