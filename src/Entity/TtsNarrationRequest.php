<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetLicenceInterface;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsChunkProgress;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Standalone TTS narration request; async lifecycle via Messenger, artifact is {@see TtsAsset}. */
#[ORM\Entity(repositoryClass: TtsNarrationRequestRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_tts_request_initial_idempotency_key', fields: ['initialIdempotencyKey'])]
#[ORM\Index(name: 'IDX_tts_request_status_mode_started', fields: ['status', 'mode', 'startedAt'])]
#[ORM\Index(name: 'IDX_tts_request_asset_mode_status', fields: ['assetId', 'mode', 'status'])]
#[ORM\Index(name: 'IDX_tts_request_ext_system', fields: ['extSystemId'])]
#[ORM\Index(name: 'IDX_tts_request_status_modified', fields: ['status', 'modifiedAt'])]
#[ORM\Index(name: 'IDX_tts_request_asset_created', fields: ['assetId', 'createdAt'])]
final class TtsNarrationRequest implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface, AssetLicenceInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    #[ORM\Column(enumType: TtsRequestStatus::class)]
    #[Serialize]
    private TtsRequestStatus $status;

    #[ORM\Column(enumType: TtsRequestMode::class)]
    #[Serialize]
    private TtsRequestMode $mode;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $failureReason;

    /**
     * Content-addressed idempotency key (licenceId+textHash+voiceSlug); cleared on terminal.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $initialIdempotencyKey;

    /**
     * Shell asset (Initial) or stable asset being regenerated; success indicated by status Done.
     */
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $assetId;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    private int $extSystemId;

    #[ORM\ManyToOne(targetEntity: AssetLicence::class)]
    #[ORM\JoinColumn(name: 'asset_licence_id', referencedColumnName: 'id', nullable: false)]
    #[Serialize(handler: EntityIdHandler::class)]
    private AssetLicence $assetLicence;

    #[ORM\Column(type: Types::STRING, length: VoiceFamily::SLUG_MAX_LENGTH, nullable: true)]
    #[Serialize]
    private ?string $voiceFamilySlug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $description;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $keywords = [];

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $authors = [];

    /**
     * Cooperative cancel: orchestrator checks before destructive swap.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $cancelRequested;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $podcastIds = [];

    /**
     * Nullified at terminal status; audit copy lives on {@see TtsAsset::$sourceTextSnapshot}.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $sourceText;

    /**
     * Transient; populated by Adm getOne controller after repo join (no ORM mapping).
     */
    #[Serialize]
    private ?TtsAsset $ttsAsset = null;

    /**
     * Transient; derived chunk progress, null for single-run requests.
     */
    #[Serialize]
    private ?TtsChunkProgress $chunkProgress = null;

    public function __construct()
    {
        $this->setStatus(TtsRequestStatus::Default);
        $this->setMode(TtsRequestMode::Default);
        $this->setStartedAt(null);
        $this->setFailureReason(null);
        $this->setInitialIdempotencyKey(null);
        $this->setAssetId(null);
        $this->setExtSystemId(0);
        $this->setVoiceFamilySlug(null);
        $this->setTitle(null);
        $this->setDescription(null);
        $this->setKeywords([]);
        $this->setAuthors([]);
        $this->setCancelRequested(false);
        $this->setPodcastIds([]);
        $this->setSourceText(null);
    }

    public function getStatus(): TtsRequestStatus
    {
        return $this->status;
    }

    public function setStatus(TtsRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMode(): TtsRequestMode
    {
        return $this->mode;
    }

    public function setMode(TtsRequestMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

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

    public function setInitialIdempotencyKey(?string $initialIdempotencyKey): self
    {
        $this->initialIdempotencyKey = $initialIdempotencyKey;

        return $this;
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }

    public function setAssetId(?string $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getExtSystemId(): int
    {
        return $this->extSystemId;
    }

    public function setExtSystemId(int $extSystemId): self
    {
        $this->extSystemId = $extSystemId;

        return $this;
    }

    public function getAssetLicence(): AssetLicence
    {
        return $this->assetLicence;
    }

    public function getLicence(): AssetLicence
    {
        return $this->getAssetLicence();
    }

    public function setAssetLicence(AssetLicence $assetLicence): self
    {
        $this->assetLicence = $assetLicence;

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

    public function isCancelRequested(): bool
    {
        return $this->cancelRequested;
    }

    public function setCancelRequested(bool $cancelRequested): self
    {
        $this->cancelRequested = $cancelRequested;

        return $this;
    }

    public function getSourceText(): ?string
    {
        return $this->sourceText;
    }

    public function setSourceText(?string $sourceText): self
    {
        $this->sourceText = $sourceText;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPodcastIds(): array
    {
        return $this->podcastIds;
    }

    /**
     * @param string[] $podcastIds
     */
    public function setPodcastIds(array $podcastIds): self
    {
        $this->podcastIds = $podcastIds;

        return $this;
    }

    public function getTtsAsset(): ?TtsAsset
    {
        return $this->ttsAsset;
    }

    public function setTtsAsset(?TtsAsset $ttsAsset): self
    {
        $this->ttsAsset = $ttsAsset;

        return $this;
    }

    public function getChunkProgress(): ?TtsChunkProgress
    {
        return $this->chunkProgress;
    }

    public function setChunkProgress(?TtsChunkProgress $chunkProgress): self
    {
        $this->chunkProgress = $chunkProgress;

        return $this;
    }
}
