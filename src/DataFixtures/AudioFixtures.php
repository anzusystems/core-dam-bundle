<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<AudioFile>
 */
final class AudioFixtures extends AbstractAssetFileFixtures
{
    public const AUDIO_ID_1 = '7994f48d-118e-4dc6-8245-98b546cda6dc';

    public function __construct(
        private readonly AudioFactory $audioFactory,
        private readonly AudioManager $audioManager,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly KeywordFixtures $keywordFixtures,
        private readonly AuthorFixtures $authorFixtures,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            AssetLicenceFixtures::class,
            CustomFormElementFixtures::class,
            KeywordFixtures::class,
            AuthorFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return AudioFile::class;
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
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $file = $this->getFile($fileSystem, 'audio_fixtures_sample.mp3');
        $audio = $this->audioFactory->createFromFile(
            $file,
            $licence,
            self::AUDIO_ID_1
        );
        $audio->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);

        $asset = $audio->getAsset();
        $asset->getAssetFlags()->setDescribed(true);
        $asset->getMetadata()->setCustomData([
            'title' => '783: Kids These Days',
            'headline' => 'Custom headline title',
            'description' => 'Custom audio description',
        ]);
        $asset->setKeywords(new ArrayCollection([
            $this->keywordFixtures->getOneFromRegistry(KeywordFixtures::KEYWORD_1),
            $this->keywordFixtures->getOneFromRegistry(KeywordFixtures::KEYWORD_2),
            $this->keywordFixtures->getOneFromRegistry(KeywordFixtures::KEYWORD_3),
        ]));

        $asset->setAuthors(new ArrayCollection([
            $this->authorFixtures->getOneFromRegistry(AuthorFixtures::AUTHOR_1),
            $this->authorFixtures->getOneFromRegistry(AuthorFixtures::AUTHOR_2),
            $this->authorFixtures->getOneFromRegistry(AuthorFixtures::AUTHOR_3),
        ]));
        $this->facadeProvider->getStatusFacade($audio)->storeAndProcess($audio, $file);

        yield $audio;
    }
}
