<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetTextsWriter;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;

/**
 * Builds the Asset + AudioFile + Route + TtsAsset aggregate from TTS provider output.
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
        private AssetFactory $assetFactory,
        private AssetManager $assetManager,
        private AssetFileManager $assetFileManager,
        private AssetFileRouteManager $routeManager,
        private Config $config,
        private TtsAssetManager $ttsAssetManager,
        private ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private AssetTextsWriter $assetTextsWriter,
    ) {
    }

    public function create(TtsAudioCreationInput $input): TtsAudioCreationResult
    {
        $now = new DateTimeImmutable();

        $audioFile = $this->buildAudioFile($input);
        $this->assetFileManager->create($audioFile, false);

        $asset = $this->resolveAsset($input, $audioFile, $now);
        $masterRoute = $this->attachStableRoute($audioFile);

        $ttsAsset = $this->buildTtsAsset($asset, $input);
        $this->ttsAssetManager->create($ttsAsset);

        return new TtsAudioCreationResult($asset, $audioFile, $ttsAsset, $input->audioFile, $masterRoute);
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

    private function resolveAsset(TtsAudioCreationInput $input, AudioFile $audioFile, DateTimeImmutable $now): Asset
    {
        if ($input->isStaging()) {
            return new Asset();
        }

        $asset = $this->assetFactory->createForAssetFile(
            assetFile: $audioFile,
            assetLicence: $input->licence,
            slotName: $this->config->getMasterSlotName(),
            id: $input->stableAssetId,
        );
        $asset->getTexts()->setDisplayTitle($this->resolveDisplayName($input, $now));
        $this->writeCustomMetadata($asset, $input);
        $this->assetManager->updateExisting($asset, false, false);

        return $asset;
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
     * Route slug must stay stable across regen — content swaps but the URL must not change.
     * The route is persisted (no flush); the orchestrator publishes it after bytes land in storage.
     */
    private function attachStableRoute(AudioFile $audioFile): AssetFileRoute
    {
        $routeSlug = StringHelper::base64UrlRandom(self::ROUTE_RANDOM_BYTES);
        $routePath = sprintf('%s/%s.%s', (string) $audioFile->getId(), $routeSlug, FfmpegService::AUDIO_EXTENSION_MP3);

        $route = (new AssetFileRoute())
            ->setUri(
                (new RouteUri())
                    ->setSlug($routeSlug)
                    ->setMain(true)
                    ->setPath($routePath)
            )
            ->setStatus(RouteStatus::Active)
            ->setMode(RouteMode::StorageCopy)
        ;
        $route->setTargetAssetFile($audioFile);
        $audioFile->getRoutes()->add($route);
        $audioFile->setMainRoute($route);
        $this->routeManager->create($route, false);

        return $route;
    }

    private function buildTtsAsset(Asset $asset, TtsAudioCreationInput $input): TtsAsset
    {
        $staging = $input->isStaging();

        return (new TtsAsset($asset))
            ->setExtResourceName($input->extResourceName)
            ->setExtId($input->extId)
            ->setVoiceFamily($input->family)
            ->setDiscriminator($input->voice->getDiscriminator())
            ->setExternalVoiceId($input->voice->getExternalVoiceId())
            ->setSourceTextHash($input->sourceTextHash)
            ->setSourceTextSnapshot($input->sourceTextSnapshot)
            ->setStatus($staging ? TtsAudioStatus::Superseding : TtsAudioStatus::Active)
            ->setStaging($staging);
    }

    private function resolveDisplayName(TtsAudioCreationInput $input, DateTimeImmutable $now): string
    {
        if (null !== $input->title && App::EMPTY_STRING !== $input->title) {
            return $input->title;
        }

        if (null !== $input->extResourceName && null !== $input->extId) {
            return sprintf('TTS %s:%s', $input->extResourceName, $input->extId);
        }

        return 'TTS standalone ' . $now->format('Y-m-d H:i:s');
    }
}
