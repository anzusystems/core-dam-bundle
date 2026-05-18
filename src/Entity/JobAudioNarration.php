<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\Entity\Embeds\JobAudioNarrationExtRef;
use AnzuSystems\CoreDamBundle\Entity\Embeds\JobAudioNarrationPodcastOptions;
use AnzuSystems\CoreDamBundle\Entity\Embeds\JobAudioNarrationSource;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsJobMode;
use AnzuSystems\CoreDamBundle\Repository\JobAudioNarrationRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * TTS audio narration Job subtype (JOINED inheritance). `openInitialKey` is the UNIQUE idempotency
 * slot for initial-mode dispatch — lifecycle invariants live in {@see JobAudioNarrationManager}.
 */
#[ORM\Entity(repositoryClass: JobAudioNarrationRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_job_audio_narration_open_initial_key', fields: ['openInitialKey'])]
#[ORM\Index(name: 'IDX_job_audio_narration_ext', fields: ['extRef.extResourceName', 'extRef.extId'])]
#[ORM\Index(name: 'IDX_job_audio_narration_stable_asset', fields: ['stableAssetId'])]
final class JobAudioNarration extends Job
{
    /**
     * Nullable unique key held only during pending/processing initial-mode jobs.
     * Cleared to NULL on terminal state. Prevents duplicate initial jobs per (extResourceName, extId, extSystem).
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, unique: true)]
    #[Serialize]
    private ?string $openInitialKey;

    #[ORM\Column(enumType: TtsJobMode::class)]
    #[Serialize]
    private TtsJobMode $mode;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $stableAssetId;

    #[ORM\Column(type: Types::GUID, length: 36, nullable: true)]
    #[Serialize]
    private ?string $assetLicenceId;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Serialize]
    private ?string $voiceFamilySlug;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $title;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $cancelRequested;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Serialize]
    private ?string $failureReason;

    #[Serialize]
    #[ORM\Embedded(class: JobAudioNarrationExtRef::class)]
    private JobAudioNarrationExtRef $extRef;

    #[Serialize]
    #[ORM\Embedded(class: JobAudioNarrationSource::class)]
    private JobAudioNarrationSource $source;

    #[Serialize]
    #[ORM\Embedded(class: JobAudioNarrationPodcastOptions::class)]
    private JobAudioNarrationPodcastOptions $podcastOptions;

    public function __construct()
    {
        parent::__construct();
        $this->setOpenInitialKey(null);
        $this->setMode(TtsJobMode::Default);
        $this->setStableAssetId(null);
        $this->setAssetLicenceId(null);
        $this->setVoiceFamilySlug(null);
        $this->setTitle(null);
        $this->setCancelRequested(false);
        $this->setFailureReason(null);
        $this->setExtRef(new JobAudioNarrationExtRef());
        $this->setSource(new JobAudioNarrationSource());
        $this->setPodcastOptions(new JobAudioNarrationPodcastOptions());
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

    public function getMode(): TtsJobMode
    {
        return $this->mode;
    }

    public function setMode(TtsJobMode $mode): self
    {
        $this->mode = $mode;

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

    public function getAssetLicenceId(): ?string
    {
        return $this->assetLicenceId;
    }

    public function setAssetLicenceId(?string $assetLicenceId): self
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

    public function isCancelRequested(): bool
    {
        return $this->cancelRequested;
    }

    public function setCancelRequested(bool $cancelRequested): self
    {
        $this->cancelRequested = $cancelRequested;

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

    public function getExtRef(): JobAudioNarrationExtRef
    {
        return $this->extRef;
    }

    public function setExtRef(JobAudioNarrationExtRef $extRef): self
    {
        $this->extRef = $extRef;

        return $this;
    }

    public function getSource(): JobAudioNarrationSource
    {
        return $this->source;
    }

    public function setSource(JobAudioNarrationSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getPodcastOptions(): JobAudioNarrationPodcastOptions
    {
        return $this->podcastOptions;
    }

    public function setPodcastOptions(JobAudioNarrationPodcastOptions $podcastOptions): self
    {
        $this->podcastOptions = $podcastOptions;

        return $this;
    }
}
