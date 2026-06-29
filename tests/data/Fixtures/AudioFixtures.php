<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Test-only audio living in a SECONDARY licence of the CMS ext system, used to exercise the cross-licence
 * (same ext system) podcast attachment: this audio (licence FIRST_SYS_SECONDARY_LICENCE) can be attached to
 * podcasts in DEFAULT_LICENCE_ID — both are in the CMS ext system. Reuses the existing audio sample file.
 *
 * @extends AbstractAssetFileFixtures<AudioFile>
 */
final class AudioFixtures extends AbstractAssetFileFixtures
{
    public const string DATA_PATH = __DIR__ . '/../../../src/Resources/fixtures/';

    public const string AUDIO_CROSS_LICENCE_ID = '1f0a4d2c-0000-6000-9000-00000c055a01';

    public function __construct(
        private readonly AudioFactory $audioFactory,
        private readonly AudioManager $audioManager,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
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
        return [AssetLicenceFixtures::class];
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AudioFile $audio */
        foreach ($progressBar->iterate($this->getData()) as $audio) {
            $audio = $this->audioManager->create($audio);
            $this->addToRegistry($audio, (string) $audio->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        /** @var AssetLicence $licence */
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE);

        $file = $this->getFile($fileSystem, 'audio_fixtures_sample.mp3');
        $audio = $this->audioFactory->createFromFile($file, $licence, self::AUDIO_CROSS_LICENCE_ID);
        $audio->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $asset = $audio->getAsset();
        $asset->getAssetFlags()->setDescribed(true);
        $asset->getMetadata()->setCustomData([
            'title' => 'Cross licence audio',
            'description' => 'Cross licence audio description',
        ]);
        $this->facadeProvider->getStatusFacade($audio)->storeAndProcess($audio, $file);

        yield $audio;
    }
}
