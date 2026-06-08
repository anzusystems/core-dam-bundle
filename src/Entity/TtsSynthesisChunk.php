<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsChunkStatus;
use AnzuSystems\CoreDamBundle\Repository\TtsSynthesisChunkRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** One synthesis unit of a multi-chunk TTS request; Pending→Processing claim is atomic to prevent double-synth. */
#[ORM\Entity(repositoryClass: TtsSynthesisChunkRepository::class)]
#[ORM\Table(name: 'tts_synthesis_chunk')]
#[ORM\UniqueConstraint(name: 'UNIQ_tts_chunk_request_ordinal', fields: ['request', 'ordinal'])]
#[ORM\Index(name: 'IDX_tts_chunk_request_status', fields: ['request', 'status'])]
#[ORM\Index(name: 'IDX_tts_chunk_status_modified', fields: ['status', 'modifiedAt'])]
#[ORM\Index(name: 'IDX_tts_chunk_status_started', fields: ['status', 'startedAt'])]
final class TtsSynthesisChunk implements UuidIdentifiableInterface, TimeTrackingInterface
{
    use UuidIdentityTrait;
    use TimeTrackingTrait;

    #[ORM\ManyToOne(targetEntity: TtsNarrationRequest::class)]
    #[ORM\JoinColumn(name: 'request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TtsNarrationRequest $request;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Serialize]
    private int $ordinal;

    #[ORM\Column(enumType: TtsChunkStatus::class)]
    #[Serialize]
    private TtsChunkStatus $status;

    // Text slice fixed at plan time.
    #[ORM\Column(type: Types::TEXT)]
    private string $sourceText;

    // ElevenLabs request-id — threaded into later chunks' previous_request_ids.
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalRequestId;

    /**
     * Relative path in per-extSystem chunk storage; set when status flips to Done.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $mp3StoragePath;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $failureReason;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    private ?DateTimeImmutable $startedAt;

    public function __construct()
    {
        $this->setStatus(TtsChunkStatus::Default);
        $this->setExternalRequestId(null);
        $this->setMp3StoragePath(null);
        $this->setFailureReason(null);
        $this->setStartedAt(null);
    }

    public function getRequest(): TtsNarrationRequest
    {
        return $this->request;
    }

    public function setRequest(TtsNarrationRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getOrdinal(): int
    {
        return $this->ordinal;
    }

    public function setOrdinal(int $ordinal): self
    {
        $this->ordinal = $ordinal;

        return $this;
    }

    public function getStatus(): TtsChunkStatus
    {
        return $this->status;
    }

    public function setStatus(TtsChunkStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSourceText(): string
    {
        return $this->sourceText;
    }

    public function setSourceText(string $sourceText): self
    {
        $this->sourceText = $sourceText;

        return $this;
    }

    public function getExternalRequestId(): ?string
    {
        return $this->externalRequestId;
    }

    public function setExternalRequestId(?string $externalRequestId): self
    {
        $this->externalRequestId = $externalRequestId;

        return $this;
    }

    public function getMp3StoragePath(): ?string
    {
        return $this->mp3StoragePath;
    }

    public function setMp3StoragePath(?string $mp3StoragePath): self
    {
        $this->mp3StoragePath = $mp3StoragePath;

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

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }
}
