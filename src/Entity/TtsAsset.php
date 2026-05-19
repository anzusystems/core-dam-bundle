<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * TTS-feature extension of {@see Asset}. 1:1 via shared primary key (asset delete cascades).
 * No inverse mapping — Asset stays bundle-agnostic; callers resolve via {@see TtsAssetRepository::findByAsset()}.
 */
#[ORM\Entity(repositoryClass: TtsAssetRepository::class)]
#[ORM\Table(name: 'tts_asset')]
#[ORM\Index(name: 'IDX_tts_asset_status', fields: ['status'])]
#[ORM\Index(name: 'IDX_tts_asset_ext_status', fields: ['extResourceName', 'extId', 'status'])]
final class TtsAsset implements TimeTrackingInterface
{
    use TimeTrackingTrait;

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $extResourceName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $extId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $extVersion = null;

    #[ORM\Column(type: Types::GUID, length: 36)]
    #[Serialize]
    private string $assetLicenceId;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $autoPodcastId = null;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $recommendedPodcastId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $includeInRecommendedPodcast = false;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Serialize]
    private string $voiceFamilySlug;

    #[ORM\Column(type: Types::GUID, length: 36)]
    #[Serialize]
    private string $voiceFamilyId;

    #[ORM\Column(enumType: VoiceDiscriminator::class)]
    #[Serialize]
    private VoiceDiscriminator $discriminator;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $externalVoiceId;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Serialize]
    private string $sourceTextHash;

    #[ORM\Column(type: Types::TEXT)]
    #[Serialize]
    private string $sourceTextSnapshot;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Serialize]
    private DateTimeImmutable $generatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $lastRegeneratedAt = null;

    #[ORM\Column(enumType: TtsAudioStatus::class)]
    #[Serialize]
    private TtsAudioStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $failureReason = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $isStaging = false;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $voiceFamilyKeywordId = null;

    /**
     * @internal Construct only via {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsAudioFactory} —
     * many non-nullable properties are populated by the factory after construction.
     */
    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
        $now = App::getAppDate();
        $this->setCreatedAt($now);
        $this->setModifiedAt($now);
    }

    public function getAsset(): Asset
    {
        return $this->asset;
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

    public function getExtVersion(): ?string
    {
        return $this->extVersion;
    }

    public function setExtVersion(?string $extVersion): self
    {
        $this->extVersion = $extVersion;

        return $this;
    }

    public function getAssetLicenceId(): string
    {
        return $this->assetLicenceId;
    }

    public function setAssetLicenceId(string $assetLicenceId): self
    {
        $this->assetLicenceId = $assetLicenceId;

        return $this;
    }

    public function getAutoPodcastId(): ?string
    {
        return $this->autoPodcastId;
    }

    public function setAutoPodcastId(?string $autoPodcastId): self
    {
        $this->autoPodcastId = $autoPodcastId;

        return $this;
    }

    public function getRecommendedPodcastId(): ?string
    {
        return $this->recommendedPodcastId;
    }

    public function setRecommendedPodcastId(?string $recommendedPodcastId): self
    {
        $this->recommendedPodcastId = $recommendedPodcastId;

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

    public function getVoiceFamilySlug(): string
    {
        return $this->voiceFamilySlug;
    }

    public function setVoiceFamilySlug(string $voiceFamilySlug): self
    {
        $this->voiceFamilySlug = $voiceFamilySlug;

        return $this;
    }

    public function getVoiceFamilyId(): string
    {
        return $this->voiceFamilyId;
    }

    public function setVoiceFamilyId(string $voiceFamilyId): self
    {
        $this->voiceFamilyId = $voiceFamilyId;

        return $this;
    }

    public function getDiscriminator(): VoiceDiscriminator
    {
        return $this->discriminator;
    }

    public function setDiscriminator(VoiceDiscriminator $discriminator): self
    {
        $this->discriminator = $discriminator;

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

    public function getSourceTextHash(): string
    {
        return $this->sourceTextHash;
    }

    public function setSourceTextHash(string $sourceTextHash): self
    {
        $this->sourceTextHash = $sourceTextHash;

        return $this;
    }

    public function getSourceTextSnapshot(): string
    {
        return $this->sourceTextSnapshot;
    }

    public function setSourceTextSnapshot(string $sourceTextSnapshot): self
    {
        $this->sourceTextSnapshot = $sourceTextSnapshot;

        return $this;
    }

    public function getGeneratedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getLastRegeneratedAt(): ?DateTimeImmutable
    {
        return $this->lastRegeneratedAt;
    }

    public function setLastRegeneratedAt(?DateTimeImmutable $lastRegeneratedAt): self
    {
        $this->lastRegeneratedAt = $lastRegeneratedAt;

        return $this;
    }

    public function getStatus(): TtsAudioStatus
    {
        return $this->status;
    }

    public function setStatus(TtsAudioStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function isStaging(): bool
    {
        return $this->isStaging;
    }

    public function setIsStaging(bool $isStaging): self
    {
        $this->isStaging = $isStaging;

        return $this;
    }

    public function getVoiceFamilyKeywordId(): ?string
    {
        return $this->voiceFamilyKeywordId;
    }

    public function setVoiceFamilyKeywordId(?string $voiceFamilyKeywordId): self
    {
        $this->voiceFamilyKeywordId = $voiceFamilyKeywordId;

        return $this;
    }
}
