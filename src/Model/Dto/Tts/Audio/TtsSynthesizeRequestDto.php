<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints as BaseAppAssert;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    '(this.getExtResourceName() === null) === (this.getExtId() === null)',
    message: 'fields.tts.extRef.must_be_both_null_or_both_present',
)]
final class TtsSynthesizeRequestDto
{
    #[Serialize]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Assert\Length(min: 10, max: 50_000, minMessage: ValidationException::ERROR_FIELD_LENGTH_MIN, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private string $text = App::EMPTY_STRING;

    #[Serialize]
    #[Assert\Length(max: 120, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $voiceFamilySlug = null;

    #[Serialize]
    private bool $includeInRecommendedPodcast = false;

    #[Serialize]
    #[Assert\Length(max: 64, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $extResourceName = null;

    #[Serialize]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $extId = null;

    #[Serialize]
    #[Assert\Length(max: 255, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    private ?string $title = null;

    /**
     * assetLicence is accepted in the body because the Adm authenticator does not yet derive
     * AssetLicence / ExtSystem from the JWT — drop once auth wiring lands upstream.
     */
    #[Serialize(handler: EntityIdHandler::class)]
    #[BaseAppAssert\NotEmptyId]
    private ?AssetLicence $assetLicence = null;

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

    public function isIncludeInRecommendedPodcast(): bool
    {
        return $this->includeInRecommendedPodcast;
    }

    public function setIncludeInRecommendedPodcast(bool $includeInRecommendedPodcast): self
    {
        $this->includeInRecommendedPodcast = $includeInRecommendedPodcast;

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
