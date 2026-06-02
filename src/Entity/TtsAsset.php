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
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * TTS-feature extension of {@see Asset}. 1:1 via shared primary key (asset delete cascades).
 * No inverse mapping — Asset stays bundle-agnostic; callers resolve via {@see TtsAssetRepository::findByAsset()}.
 *
 * Shape = snapshot of generation moment. Derivable state (tenant config, PodcastEpisode membership,
 * request audit) lives elsewhere.
 */
#[ORM\Entity(repositoryClass: TtsAssetRepository::class)]
#[ORM\Table(name: 'tts_asset')]
#[ORM\Index(name: 'IDX_tts_asset_status', fields: ['status'])]
#[ORM\Index(name: 'IDX_tts_asset_ext_status', fields: ['extResourceName', 'extId', 'status'])]
#[ORM\Index(name: 'IDX_tts_asset_content', fields: ['sourceTextHash', 'voiceFamily'])]
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

    /**
     * Generation-time snapshot. Hard-delete is blocked app-side by VoiceFamilyManager throwing
     * {@see DependencyExistsException}; DB FK is the safety net.
     * Eager fetch: every read site dereferences the family — lazy would N+1 the SYS list endpoint.
     */
    #[ORM\ManyToOne(targetEntity: VoiceFamily::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'voice_family_id', referencedColumnName: 'id', nullable: false)]
    #[Serialize(handler: EntityIdHandler::class)]
    private VoiceFamily $voiceFamily;

    #[ORM\Column(enumType: VoiceDiscriminator::class)]
    #[Serialize]
    private VoiceDiscriminator $discriminator;

    /**
     * Historical snapshot — live Voice may be replaced/deactivated, but this audio was synthesised
     * with THIS provider voice ID.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $externalVoiceId;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Serialize]
    private string $sourceTextHash;

    #[ORM\Column(type: Types::TEXT)]
    #[Serialize]
    private string $sourceTextSnapshot;

    #[ORM\Column(enumType: TtsAudioStatus::class)]
    #[Serialize]
    private TtsAudioStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $failureReason = null;

    /**
     * True while this row is the regen-staging counterpart of the stable asset — flipped to false
     * by {@see TtsAssetManager::markActive} at swap completion.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $staging = false;

    /**
     * GUID snapshot of the family keywords applied at generation (no FK — keywords may be deleted);
     * lets {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsRequestOrchestrator::syncFamilyKeywords} reconcile on regen.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $voiceFamilyKeywordIds = [];

    /**
     * @internal Construct only via {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsAudioFactory}.
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

    #[Serialize]
    public function getAssetId(): string
    {
        return (string) $this->asset->getId();
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

    public function getVoiceFamily(): VoiceFamily
    {
        return $this->voiceFamily;
    }

    public function setVoiceFamily(VoiceFamily $voiceFamily): self
    {
        $this->voiceFamily = $voiceFamily;

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
        return $this->staging;
    }

    public function setStaging(bool $staging): self
    {
        $this->staging = $staging;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getVoiceFamilyKeywordIds(): array
    {
        return $this->voiceFamilyKeywordIds;
    }

    /**
     * @param string[] $voiceFamilyKeywordIds
     */
    public function setVoiceFamilyKeywordIds(array $voiceFamilyKeywordIds): self
    {
        $this->voiceFamilyKeywordIds = $voiceFamilyKeywordIds;

        return $this;
    }
}
