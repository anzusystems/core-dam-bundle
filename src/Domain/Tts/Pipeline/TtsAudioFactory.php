<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;

/**
 * Builds the AudioFile (+ route + TtsAsset) aggregate for a TTS Asset from provider output.
 *
 * The asset always pre-exists: {@see create()} attaches the master audio onto the file-less audio shell
 * reserved at dispatch (initial), while {@see buildReplacementMaster()} builds a fresh, not-yet-slotted master
 * for a regeneration that {@see AssetSwap} later promotes into the live asset (keeping the old audio for a grace
 * period). Either way the asset id — and thus the CMS media key — stays stable.
 *
 * The audio bytes are NOT persisted to final storage here — the file is left in the {@see AssetFileProcessStatus::Uploaded}
 * state with a pre-built stable route entity. The orchestrator subsequently drives the standard
 * pipeline ({@see \AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade::storeAndProcess()}
 * + {@see \AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteFacade::makePublic()})
 * so TTS shares the same store / attribute-extract / publish flow as a regular audio upload.
 *
 * Caller owns the surrounding transaction and flush.
 */
final readonly class TtsAudioFactory
{
    private const int ROUTE_RANDOM_BYTES = 16;

    public function __construct(
        private AudioFactory $audioFactory,
        private AssetSlotFactory $assetSlotFactory,
        private AssetManager $assetManager,
        private AssetFileManager $assetFileManager,
        private AssetFileRouteFactory $routeFactory,
        private Config $config,
        private TtsAssetManager $ttsAssetManager,
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private AssetTextsWriter $assetTextsWriter,
    ) {
    }

    /**
     * Initial generation: attach the master audio onto the pre-created (Draft, file-less) shell asset and
     * create its TtsAsset. The asset keeps the id reserved at dispatch.
     */
    public function create(TtsAudioCreationInput $input, Asset $asset): TtsAudioCreationResult
    {
        $now = new DateTimeImmutable();

        $audioFile = $this->buildAudioFile($input);
        $this->assetFileManager->create($audioFile, false);

        $this->assetSlotFactory->createRelation(
            asset: $asset,
            assetFile: $audioFile,
            slotName: $this->config->getMasterSlotName(),
            flush: false,
        );
        $asset->getTexts()->setDisplayTitle($this->resolveDisplayName($input, $now));
        $this->writeCustomMetadata($asset, $input);
        $this->assetManager->updateExisting($asset, false, false);

        $masterRoute = $this->attachStableRoute($audioFile);

        $ttsAsset = $this->buildTtsAsset($asset, $input);
        $this->ttsAssetManager->create($ttsAsset);

        return new TtsAudioCreationResult($asset, $audioFile, $ttsAsset, $input->audioFile, $masterRoute);
    }

    /**
     * Regeneration: build a fresh master AudioFile owned by the stable asset (its `asset` FK is set so the
     * required relation holds) but NOT yet attached to any slot — the orchestrator materialises + publishes
     * its bytes first, then {@see AssetSwap} repoints the live master slot at it. The previous audio (and its
     * public CDN URL) is left untouched until the swap demotes it with a grace period. The result carries the
     * still-active stable {@see TtsAsset} (updated in place by the swap, not recreated here).
     */
    public function buildReplacementMaster(TtsAudioCreationInput $input, Asset $stableAsset, TtsAsset $stableTts): TtsAudioCreationResult
    {
        $audioFile = $this->buildAudioFile($input);
        $audioFile->setAsset($stableAsset);
        $this->assetFileManager->create($audioFile, false);

        $masterRoute = $this->attachStableRoute($audioFile);

        return new TtsAudioCreationResult($stableAsset, $audioFile, $stableTts, $input->audioFile, $masterRoute);
    }

    private function buildAudioFile(TtsAudioCreationInput $input): AudioFile
    {
        $audioFile = $this->audioFactory->createBlankAudio($input->licence);
        $audioFile->getAssetAttributes()
            ->setMimeType(AudioMimeTypes::MimeMpeg->value)
            ->setStatus(AssetFileProcessStatus::Uploaded)
            ->setCreateStrategy(AssetFileCreateStrategy::Storage)
            ->setSize((int) ($input->audioFile->getSize() ?: 0))
        ;

        return $audioFile;
    }

    /**
     * Writes caller title/description into the asset custom-data via the ext-system `tts_metadata_map`.
     * The map's source is the {@see TtsAudioCreationInput} (scalar `title`/`description`), not the asset,
     * so map source paths are those props. No-op when unconfigured.
     */
    private function writeCustomMetadata(Asset $asset, TtsAudioCreationInput $input): void
    {
        $audioConfig = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration(
            $input->licence->getExtSystem()->getSlug()
        );

        $this->assetTextsWriter->writeValues($input, $asset, $audioConfig->getTtsMetadataMap());
    }

    /**
     * Each (re)generation gets its own stable route: the slug/path derive from the new AudioFile id, so a
     * regeneration produces a NEW public-bucket path = NEW public URL, while the old file keeps its own.
     * The route is persisted (no flush); the orchestrator publishes it after bytes land in storage.
     */
    private function attachStableRoute(AudioFile $audioFile): AssetFileRoute
    {
        $routeSlug = StringHelper::base64UrlRandom(self::ROUTE_RANDOM_BYTES);
        $routePath = sprintf('%s/%s.%s', (string) $audioFile->getId(), $routeSlug, FfmpegService::AUDIO_EXTENSION_MP3);

        return $this->routeFactory->createPrebuiltAudioRoute($audioFile, $routeSlug, $routePath);
    }

    private function buildTtsAsset(Asset $asset, TtsAudioCreationInput $input): TtsAsset
    {
        return (new TtsAsset($asset))
            ->setVoiceFamily($input->family)
            ->setDiscriminator($input->voice->getDiscriminator())
            ->setExternalVoiceId($input->voice->getExternalVoiceId())
            ->setSourceTextHash($input->sourceTextHash)
            ->setSourceTextSnapshot($input->sourceTextSnapshot)
            ->setStatus(TtsAudioStatus::Active);
    }

    private function resolveDisplayName(TtsAudioCreationInput $input, DateTimeImmutable $now): string
    {
        if (null !== $input->title && App::EMPTY_STRING !== $input->title) {
            return $input->title;
        }

        return 'TTS standalone ' . $now->format('Y-m-d H:i:s');
    }
}
