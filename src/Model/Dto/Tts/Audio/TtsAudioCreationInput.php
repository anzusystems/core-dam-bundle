<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\JobAudioNarration;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Voice\ResolvedVoice;
use InvalidArgumentException;

final readonly class TtsAudioCreationInput
{
    /**
     * @throws InvalidArgumentException if the ($extResourceName, $extId) both-null/both-non-null invariant is violated
     */
    public function __construct(
        public AdapterFile $audioFile,
        public VoiceFamily $family,
        public ResolvedVoice $voice,
        public AssetLicence $licence,
        public string $sourceTextHash,
        public string $sourceTextSnapshot,
        public ?string $extResourceName = null,
        public ?string $extId = null,
        public ?string $extVersion = null,
        public ?string $autoPodcastId = null,
        public ?string $recommendedPodcastId = null,
        public bool $includeInRecommendedPodcast = false,
        public ?string $title = null,
        public bool $staging = false,
    ) {
        if ((null === $extResourceName) !== (null === $extId)) {
            throw new InvalidArgumentException(
                'extResourceName and extId must be both null or both non-null.'
            );
        }
    }

    public function isStaging(): bool
    {
        return $this->staging;
    }

    public static function forInitialJob(
        JobAudioNarration $job,
        AdapterFile $audioFile,
        VoiceFamily $family,
        ResolvedVoice $voice,
        AssetLicence $licence,
        string $sourceText,
    ): self {
        $extRef = $job->getExtRef();
        $podcast = $job->getPodcastOptions();

        return new self(
            audioFile: $audioFile,
            family: $family,
            voice: $voice,
            licence: $licence,
            sourceTextHash: (string) $job->getSource()->getHash(),
            sourceTextSnapshot: $sourceText,
            extResourceName: $extRef->getExtResourceName(),
            extId: $extRef->getExtId(),
            extVersion: $extRef->getExtVersion(),
            autoPodcastId: $podcast->getAutoPodcastId(),
            recommendedPodcastId: $podcast->getRecommendedPodcastId(),
            includeInRecommendedPodcast: $podcast->isIncludeInRecommended(),
            title: $job->getTitle(),
        );
    }

    public static function forStagingSwap(
        JobAudioNarration $job,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        VoiceFamily $family,
        ResolvedVoice $voice,
        AssetLicence $licence,
    ): self {
        $extRef = $job->getExtRef();

        return new self(
            audioFile: $audioFile,
            family: $family,
            voice: $voice,
            licence: $licence,
            sourceTextHash: $stableTts->getSourceTextHash(),
            sourceTextSnapshot: $stableTts->getSourceTextSnapshot(),
            extResourceName: $extRef->getExtResourceName(),
            extId: $extRef->getExtId(),
            extVersion: $extRef->getExtVersion(),
            title: $job->getTitle(),
            staging: true,
        );
    }
}
