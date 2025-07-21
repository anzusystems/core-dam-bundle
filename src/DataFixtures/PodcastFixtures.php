<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastDates;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Podcast>
 */
final class PodcastFixtures extends AbstractFixtures
{
    public const string PODCAST_1 = '5edeb44d-c64b-4357-957d-688d9cf7e63a';
    public const string PODCAST_2 = '8fe7196e-1480-41b6-b0b3-1c73c79f3452';
    public const string PODCAST_3 = '5edeb44d-c64b-4357-957d-688d9cf7e62a';
    public const string PODCAST_4 = '5edeb44e-c64b-4357-957d-688d9cf7e62a';

    public function __construct(
        private readonly PodcastManager $podcastManager,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly ImagePreviewFactory $imagePreviewFactory,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['dev', 'test'];
    }

    public static function getDependencies(): array
    {
        return [
            ImageFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return Podcast::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Podcast $podcast */
        foreach ($progressBar->iterate($this->getData()) as $podcast) {
            $podcast = $this->podcastManager->create($podcast);
            $this->addToRegistry($podcast, $podcast->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        /** @var ImageFile $imageFile */
        $imageFile = $this->imageFileRepository->find(ImageFixtures::IMAGE_ID_1_2);

        $podcast = (new Podcast())
            ->setId(self::PODCAST_1)
            ->setDates(
                (new PodcastDates())
                    ->setImportFrom(App::getAppDate()->modify('-3 month'))
            )
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('Dobré ráno')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://anchor.fm/s/8a651488/podcast/rss')
                    ->setMobileOrderPosition(100)
                    ->setWebOrderPosition(100)
            )
            ->setFlags(
                (new PodcastFlags())
                    ->setMobilePublicExportEnabled(true)
                    ->setWebPublicExportEnabled(true)
            )
            ->setLicence($licence);

        $podcast->setImagePreview(
            $this->imagePreviewFactory->createFromImageFile(
                imageFile: $imageFile,
                flush: false
            )
        );

        yield $podcast;

        yield (new Podcast())
            ->setId(self::PODCAST_2)
            ->setDates(
                (new PodcastDates())
                    ->setImportFrom(App::getAppDate()->modify('-3 month'))
            )
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('Klik')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://anchor.fm/s/4d8e8b48/podcast/rss')
                    ->setMobileOrderPosition(50)
                    ->setWebOrderPosition(200)
            )
            ->setFlags(
                (new PodcastFlags())
                    ->setMobilePublicExportEnabled(true)
                    ->setWebPublicExportEnabled(true)
            )
            ->setLicence($licence);

        yield (new Podcast())
            ->setId(self::PODCAST_3)
            ->setDates(
                (new PodcastDates())
                    ->setImportFrom(App::getAppDate()->modify('-3 month'))
            )
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('Rozprávky SME')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://anchor.fm/s/7758ecd4/podcast/rss')
                    ->setMobileOrderPosition(300)
                    ->setWebOrderPosition(300)
            )
            ->setLicence($licence);

        yield (new Podcast())
            ->setId(self::PODCAST_4)
            ->setDates(
                (new PodcastDates())
                    ->setImportFrom(App::getAppDate()->modify('-3 month'))
            )
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('Test feed')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://anchor.fm/s/db2e247c/podcast/rss')
                    ->setMobileOrderPosition(400)
                    ->setWebOrderPosition(400)
            )
            ->setLicence($licence);
    }
}
