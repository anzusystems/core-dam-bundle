<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Podcast>
 */
final class PodcastFixtures extends AbstractAssetFileFixtures
{
    public const PODCAST_1 = '8fe7196e-1480-41b6-b0b3-1c73c79f3452';
    public const PODCAST_2 = '5edeb44d-c64b-4357-957d-688d9cf7e63a';

    public function __construct(
        private readonly PodcastManager $podcastManager
    ) {
    }

    public static function getIndexKey(): string
    {
        return Podcast::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        $this->configureAssignedGenerator();
        /** @var Podcast $podcast */
        foreach ($progressBar->iterate($this->getData()) as $podcast) {
            $podcast = $this->podcastManager->create($podcast);
            $this->addToRegistry($podcast, $podcast->getId());
        }
    }

    private function getData(): Generator
    {
        $licence = $this->entityManager->find(AssetLicence::class, 1);

        yield (new Podcast())
            ->setId(self::PODCAST_1)
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('This American Life')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://www.thisamericanlife.org/podcast/rss.xml')
            )
            ->setLicence($licence)
        ;

        yield (new Podcast())
            ->setId(self::PODCAST_2)
            ->setTexts(
                (new PodcastTexts())
                    ->setTitle('Vedator')
            )
            ->setAttributes(
                (new PodcastAttributes())
                    ->setRssUrl('https://feed.podbean.com/vedatorskypodcast/feed.xml')
            )
            ->setLicence($licence)
        ;
    }
}
