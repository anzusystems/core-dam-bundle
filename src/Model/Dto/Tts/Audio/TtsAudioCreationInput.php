<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio;

use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use InvalidArgumentException;

final readonly class TtsAudioCreationInput
{
    /**
     * @throws InvalidArgumentException if the ($extResourceName, $extId) both-null/both-non-null invariant is violated
     */
    public function __construct(
        public AdapterFile $audioFile,
        public VoiceFamily $family,
        public Voice $voice,
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

    public static function forInitialRequest(
        TtsNarrationRequest $request,
        AdapterFile $audioFile,
        VoiceFamily $family,
        Voice $voice,
        AssetLicence $licence,
        string $sourceText,
    ): self {
        $extRef = $request->getExtRef();
        $podcast = $request->getPodcastOptions();

        return new self(
            audioFile: $audioFile,
            family: $family,
            voice: $voice,
            licence: $licence,
            sourceTextHash: (string) $request->getSource()->getHash(),
            sourceTextSnapshot: $sourceText,
            extResourceName: $extRef->getExtResourceName(),
            extId: $extRef->getExtId(),
            extVersion: $extRef->getExtVersion(),
            autoPodcastId: $podcast->getAutoPodcastId(),
            recommendedPodcastId: $podcast->getRecommendedPodcastId(),
            includeInRecommendedPodcast: $podcast->isIncludeInRecommended(),
            title: $request->getTitle(),
        );
    }

    public static function forStagingSwap(
        TtsNarrationRequest $request,
        TtsAsset $stableTts,
        AdapterFile $audioFile,
        VoiceFamily $family,
        Voice $voice,
        AssetLicence $licence,
    ): self {
        $extRef = $request->getExtRef();

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
            title: $request->getTitle(),
            staging: true,
        );
    }
}
