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

/** TTS extension of {@see Asset} — 1:1 shared PK, no inverse mapping; generation-moment snapshot. */
#[ORM\Entity(repositoryClass: TtsAssetRepository::class)]
#[ORM\Index(name: 'IDX_tts_asset_status', fields: ['status'])]
#[ORM\Index(name: 'IDX_tts_asset_content', fields: ['sourceTextHash', 'voiceFamily'])]
final class TtsAsset implements TimeTrackingInterface
{
    use TimeTrackingTrait;

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    /**
     * Generation-time snapshot; serialized as bare id, so a LAZY proxy id is enough (no extra load).
     */
    #[ORM\ManyToOne(targetEntity: VoiceFamily::class)]
    #[ORM\JoinColumn(name: 'voice_family_id', referencedColumnName: 'id', nullable: false)]
    #[Serialize(handler: EntityIdHandler::class)]
    private VoiceFamily $voiceFamily;

    #[ORM\Column(enumType: VoiceDiscriminator::class)]
    #[Serialize]
    private VoiceDiscriminator $provider;

    /**
     * Historical snapshot of the provider voice ID used for this synthesis.
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

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $mainImageFileId = null;

    /**
     * @internal Use {@see \AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline\TtsAudioFactory}.
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

    public function getVoiceFamily(): VoiceFamily
    {
        return $this->voiceFamily;
    }

    public function setVoiceFamily(VoiceFamily $voiceFamily): self
    {
        $this->voiceFamily = $voiceFamily;

        return $this;
    }

    public function getProvider(): VoiceDiscriminator
    {
        return $this->provider;
    }

    public function setProvider(VoiceDiscriminator $provider): self
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

    public function getMainImageFileId(): ?string
    {
        return $this->mainImageFileId;
    }

    public function setMainImageFileId(?string $mainImageFileId): self
    {
        $this->mainImageFileId = $mainImageFileId;

        return $this;
    }
}
