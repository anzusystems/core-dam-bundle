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

/** Builds AudioFile + route + TtsAsset from provider output; caller owns the transaction. */
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
     * Attach master audio onto the pre-created shell asset and create its TtsAsset.
     */
    public function create(TtsAudioCreationInput $input, Asset $asset): TtsAudioCreationResult
    {
        $now = new DateTimeImmutable();

        $audioFile = $this->buildAudioFile($input);
        $this->assetFileManager->create($audioFile, flush: false);

        $this->assetSlotFactory->createRelation(
            asset: $asset,
            assetFile: $audioFile,
            slotName: $this->config->getMasterSlotName(),
            flush: false,
        );
        $asset->getTexts()->setDisplayTitle($this->resolveDisplayName($input, $now));
        $this->writeCustomMetadata($asset, $input);
        $this->assetManager->updateExisting($asset, flush: false, trackModification: false);

        $masterRoute = $this->attachStableRoute($audioFile);

        $ttsAsset = $this->buildTtsAsset($asset, $input);
        $this->ttsAssetManager->create($ttsAsset);

        return new TtsAudioCreationResult($asset, $audioFile, $ttsAsset, $input->audioFile, $masterRoute);
    }

    /**
     * Build a fresh master AudioFile owned by the stable asset but not yet slotted; AssetSwap promotes it.
     */
    public function buildReplacementMaster(
        TtsAudioCreationInput $input,
        Asset $stableAsset,
        TtsAsset $stableTts,
        DateTimeImmutable $orphanExpireAt,
    ): TtsAudioCreationResult {
        $audioFile = $this->buildAudioFile($input);
        $audioFile->setAsset($stableAsset);
        $this->assetFileManager->create($audioFile, flush: false);
        // Unslotted orphan until swap — safety expireAt so a pre-swap crash leaves it reapable.
        $audioFile->setExpireAt($orphanExpireAt);

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
     * Writes caller title/description into asset custom-data via the ext-system tts_metadata_map. No-op when unconfigured.
     */
    private function writeCustomMetadata(Asset $asset, TtsAudioCreationInput $input): void
    {
        $audioConfig = $this->extSystemConfigurationProvider->getAudioExtSystemConfiguration(
            $input->licence->getExtSystem()->getSlug()
        );

        $this->assetTextsWriter->writeValues($input, $asset, $audioConfig->getTtsMetadataMap());
    }

    /**
     * Each (re)generation gets a new route derived from the AudioFile id — a new public URL per regen.
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
            ->setProvider($input->voice->getDiscriminator())
            ->setExternalVoiceId($input->voice->getExternalVoiceId())
            ->setSourceTextHash($input->sourceTextHash)
            ->setSourceTextSnapshot($input->sourceTextSnapshot)
            ->setMainImageFileId($input->mainImageFileId)
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
