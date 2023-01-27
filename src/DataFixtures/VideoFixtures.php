<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoManager;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<VideoFile>
 */
final class VideoFixtures extends AbstractAssetFileFixtures
{
    public const VIDEO_ID_1 = 'aa967cf4-0ea9-499e-be2a-13bf0b63eabe';

    public function __construct(
        private readonly VideoManager $videoManager,
        private readonly VideoFactory $videoFactory,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly AuthorFixtures $authorFixtures,
        private readonly KeywordFixtures $keywordFixtures,
        private readonly DistributionCategoryRepository $distributionCategoryRepository,
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            AssetLicenceFixtures::class,
            CustomFormElementFixtures::class,
            AuthorFixtures::class,
            KeywordFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return VideoFile::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var VideoFile $video */
        foreach ($progressBar->iterate($this->getData()) as $video) {
            $video = $this->videoManager->create($video);

            $this->addToRegistry($video, (int) $video->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $file = $this->getFile($fileSystem, 'video_fixtures_sample.mp4');
        $video = $this->videoFactory->createFromFile(
            $file,
            $licence,
            self::VIDEO_ID_1
        );

        $asset = $video->getAsset();
        $asset->getAssetFlags()->setDescribed(true);
        $asset->getMetadata()->setCustomData([
            'title' => 'Video title',
            'headline' => 'Custom headline title',
            'description' => 'Custom video description',
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

        $asset->setDistributionCategory(
            $this->distributionCategoryRepository->findOneBy([
                'name' => 'Publicistika',
            ])
        );
        $video->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($video)->storeAndProcess($video, $file);

        yield $video;
    }
}
