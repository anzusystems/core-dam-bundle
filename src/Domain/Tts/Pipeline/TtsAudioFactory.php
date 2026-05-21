<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Pipeline;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\FileProcessor\AudioAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\Lifecycle\TtsAssetManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationInput;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioCreationResult;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AudioMimeTypes;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsAudioStatus;
use DateTimeImmutable;
use League\Flysystem\FilesystemException;

/**
 * Materializes TTS provider MP3 bytes into an Asset + AudioFile + Route + TtsAsset aggregate.
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
        private NameGenerator $nameGenerator,
        private AudioAttributesProcessor $attributesProcessor,
        private FileSystemProvider $fileSystemProvider,
        private Config $config,
        private TtsAssetManager $ttsAssetManager,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function create(TtsAudioCreationInput $input): TtsAudioCreationResult
    {
        $now = new DateTimeImmutable();

        $audioFile = $this->buildAudioFile($input);
        $this->persistToStorage($audioFile, $input->audioFile);
        $this->attributesProcessor->process($audioFile, $input->audioFile);
        $this->assetFileManager->create($audioFile, false);

        $asset = $this->resolveAsset($input, $audioFile, $now);
        $this->attachStableRoute($audioFile);

        $ttsAsset = $this->buildTtsAsset($asset, $input);
        $this->ttsAssetManager->create($ttsAsset);

        return new TtsAudioCreationResult($asset, $audioFile, $ttsAsset, $input->audioFile);
    }

    private function buildAudioFile(TtsAudioCreationInput $input): AudioFile
    {
        $audioFile = $this->audioFactory->createBlankAudio($input->licence);
        $audioFile->getAssetAttributes()
            ->setMimeType(AudioMimeTypes::MimeMpeg->value)
            ->setStatus(AssetFileProcessStatus::Processed)
            ->setCreateStrategy(AssetFileCreateStrategy::Storage)
            ->setSize((int) ($input->audioFile->getSize() ?: 0))
        ;

        return $audioFile;
    }

    /**
     * Streams the tmp MP3 into permanent storage without buffering the full payload in memory.
     *
     * @throws FilesystemException
     */
    private function persistToStorage(AudioFile $audioFile, AdapterFile $tmpFile): void
    {
        $generatedPath = $this->nameGenerator->generatePath(
            extension: FfmpegService::AUDIO_EXTENSION_MP3,
            dateDirPath: true,
        );
        $filesystem = $this->fileSystemProvider->getFilesystemByStorable($audioFile);
        $filesystem->writeStream($generatedPath->getRelativePath(), $tmpFile->getLocalFilesystem()->readStream($tmpFile->getAdapterPath()));

        $audioFile->getAssetAttributes()->setFilePath($generatedPath->getRelativePath());
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
        );
        $asset->getTexts()->setDisplayTitle($this->resolveDisplayName($input, $now));
        $this->assetManager->updateExisting($asset, false, false);

        return $asset;
    }

    /**
     * Route slug must stay stable across regen — content swaps but the URL must not change.
     */
    private function attachStableRoute(AudioFile $audioFile): void
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
