<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Embeds\TtsNarrationRequestExtRef;
use AnzuSystems\CoreDamBundle\Entity\Embeds\TtsNarrationRequestSource;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestMode;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsRequestStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Standalone TTS request — NOT a {@see Job} subtype. Owns its own async lifecycle via Messenger;
 * the produced artifact is a {@see TtsAsset} linked via {@see resultAssetId} on Done.
 */
#[ORM\Entity(repositoryClass: TtsNarrationRequestRepository::class)]
#[ORM\Table(name: 'tts_narration_request')]
#[ORM\UniqueConstraint(name: 'UNIQ_tts_request_open_initial_key', fields: ['openInitialKey'])]
#[ORM\Index(name: 'IDX_tts_request_ext', fields: ['extRef.extResourceName', 'extRef.extId'])]
#[ORM\Index(name: 'IDX_tts_request_status_mode', fields: ['status', 'mode'])]
#[ORM\Index(name: 'IDX_tts_request_stable_mode_status', fields: ['stableAssetId', 'mode', 'status'])]
#[ORM\Index(name: 'IDX_tts_request_result_asset', fields: ['resultAssetId'])]
#[ORM\Index(name: 'IDX_tts_request_ext_system', fields: ['extSystemId'])]
final class TtsNarrationRequest implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
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
     * Initial-mode idempotency key; cleared on terminal so the (extResource,extId,extSystem) slot frees up.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $openInitialKey;

    /**
     * Stable Asset being regenerated (only set for Regenerate mode).
     */
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $stableAssetId;

    /**
     * Asset produced (Initial) or updated (Regenerate) by this request. Set on Done transition.
     */
    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $resultAssetId;

    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    private int $extSystemId;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Serialize]
    private ?int $assetLicenceId;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Serialize]
    private ?string $voiceFamilySlug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $title;

    /**
     * Asset description (e.g. article perex); applied once on initial generation.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $description;

    /**
     * @var string[] caller keyword names, matched + linked on initial generation
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $keywords = [];

    /**
     * @var string[] caller author display names, best-effort matched on initial generation
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $authors = [];

    /**
     * Cooperative cancel flag — orchestrator checks it before destructive swap.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $cancelRequested;

    /**
     * Podcast IDs to attach this asset to after synthesis. Stored as JSON array of integers.
     * CMS computes the list at dispatch time; DAM applies it generically post-synthesis.
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $podcastIds = [];

    #[Serialize]
    #[ORM\Embedded(class: TtsNarrationRequestExtRef::class)]
    private TtsNarrationRequestExtRef $extRef;

    #[Serialize]
    #[ORM\Embedded(class: TtsNarrationRequestSource::class)]
    private TtsNarrationRequestSource $source;

    /**
     * Transient (non-persisted) — populated by the Adm getOne controller after a repo join.
     * Serialized into the API response to avoid a separate DTO wrapper.
     * No ORM mapping on purpose: Doctrine ignores unmapped properties.
     */
    #[Serialize]
    private ?TtsAsset $ttsAsset = null;

    public function __construct()
    {
        $this->setStatus(TtsRequestStatus::Default);
        $this->setMode(TtsRequestMode::Default);
        $this->setStartedAt(null);
        $this->setFailureReason(null);
        $this->setOpenInitialKey(null);
        $this->setStableAssetId(null);
        $this->setResultAssetId(null);
        $this->setExtSystemId(0);
        $this->setAssetLicenceId(null);
        $this->setVoiceFamilySlug(null);
        $this->setTitle(null);
        $this->setDescription(null);
        $this->setKeywords([]);
        $this->setAuthors([]);
        $this->setCancelRequested(false);
        $this->setPodcastIds([]);
        $this->setExtRef(new TtsNarrationRequestExtRef());
        $this->setSource(new TtsNarrationRequestSource());
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

    public function getOpenInitialKey(): ?string
    {
        return $this->openInitialKey;
    }

    public function setOpenInitialKey(?string $openInitialKey): self
    {
        $this->openInitialKey = $openInitialKey;

        return $this;
    }

    public function getStableAssetId(): ?string
    {
        return $this->stableAssetId;
    }

    public function setStableAssetId(?string $stableAssetId): self
    {
        $this->stableAssetId = $stableAssetId;

        return $this;
    }

    public function getResultAssetId(): ?string
    {
        return $this->resultAssetId;
    }

    public function setResultAssetId(?string $resultAssetId): self
    {
        $this->resultAssetId = $resultAssetId;

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

    public function getAssetLicenceId(): ?int
    {
        return $this->assetLicenceId;
    }

    public function setAssetLicenceId(?int $assetLicenceId): self
    {
        $this->assetLicenceId = $assetLicenceId;

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

    public function getExtRef(): TtsNarrationRequestExtRef
    {
        return $this->extRef;
    }

    public function setExtRef(TtsNarrationRequestExtRef $extRef): self
    {
        $this->extRef = $extRef;

        return $this;
    }

    public function getSource(): TtsNarrationRequestSource
    {
        return $this->source;
    }

    public function setSource(TtsNarrationRequestSource $source): self
    {
        $this->source = $source;

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
}
