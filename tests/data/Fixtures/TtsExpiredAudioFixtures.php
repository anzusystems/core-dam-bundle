<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Seeds two expired TTS audio files for {@see TtsClearExpiredAudioCommandTest}: an orphan (asset FK `NULL`, as left by legacy deletes)
 * reaped first, and a superseded master; both keep their main route.
 *
 * @extends AbstractFixtures<AudioFile>
 */
final class TtsExpiredAudioFixtures extends AbstractFixtures
{
    public const string ORPHAN_AUDIO_ID = '0f1c3a52-6a40-4a31-9b64-3f0a1d5c7e01';
    public const string SUPERSEDED_AUDIO_ID = '0f1c3a52-6a40-4a31-9b64-3f0a1d5c7e02';

    private const string SAMPLE_FILE = 'audio_fixtures_sample.mp3';

    public function __construct(
        private readonly AudioFactory $audioFactory,
        private readonly AudioManager $audioManager,
        private readonly AssetFileRouteManager $assetFileRouteManager,
        private readonly AssetLicenceRepository $assetLicenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return AudioFile::class;
    }

    public static function getDependencies(): array
    {
        return [BaseAssetLicenceFixtures::class];
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AssetLicence $licence */
        $licence = $this->assetLicenceRepository->find(BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $orphan = $this->createExpiredAudio($licence, self::ORPHAN_AUDIO_ID, '-2 days');
        $superseded = $this->createExpiredAudio($licence, self::SUPERSEDED_AUDIO_ID, '-1 day');
        $this->createMainRoute($orphan, 'tts-expired-orphan');
        $this->createMainRoute($superseded, 'tts-expired-superseded');
        $this->entityManager->flush();

        // Seeds the production states the reaper meets: a legacy orphan (asset row deleted before asset deletion removed
        // detached TTS audio, FK `SET NULL`) and a superseded master (keeps the asset FK, no slot, no longer the main file).
        $connection = $this->entityManager->getConnection();
        $orphanAssetId = (string) $orphan->getAsset()->getId();
        $connection->executeStatement('DELETE FROM asset_slot WHERE asset_id = ?', [$orphanAssetId]);
        $connection->executeStatement('DELETE FROM asset WHERE id = ?', [$orphanAssetId]);
        $connection->executeStatement('UPDATE asset SET main_file_id = NULL WHERE main_file_id = ?', [self::SUPERSEDED_AUDIO_ID]);
        $connection->executeStatement('DELETE FROM asset_slot WHERE audio_id = ?', [self::SUPERSEDED_AUDIO_ID]);

        foreach ([$orphan, $superseded] as $audio) {
            $asset = $audio->getAsset();
            foreach ([...$asset->getSlots(), $asset, ...$audio->getRoutes(), $audio] as $entity) {
                $this->entityManager->detach($entity);
            }
        }
        $progressBar->advance(2);
    }

    private function createExpiredAudio(AssetLicence $licence, string $id, string $expiredAgo): AudioFile
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(AbstractAssetFileFixtures::DATA_PATH);
        $file = new AdapterFile(
            path: AbstractAssetFileFixtures::DATA_PATH . self::SAMPLE_FILE,
            adapterPath: self::SAMPLE_FILE,
            filesystem: $fileSystem
        );

        /** @var AudioFile $audio */
        $audio = $this->audioFactory->createFromFile($file, $licence, $id);
        $audio->setExpireAt(App::getAppDate()->modify($expiredAgo));

        return $this->audioManager->create($audio);
    }

    private function createMainRoute(AudioFile $audio, string $slug): void
    {
        $route = (new AssetFileRoute())
            ->setUri(
                (new RouteUri())
                    ->setSlug($slug)
                    ->setMain(true)
                    ->setPath('tts/' . $slug . '.mp3')
            )
            ->setMode(RouteMode::Direct)
            ->setStatus(RouteStatus::Active)
        ;
        $route->setTargetAssetFile($audio);
        $audio->getRoutes()->add($route);

        $this->assetFileRouteManager->create($route, flush: false);
    }
}
