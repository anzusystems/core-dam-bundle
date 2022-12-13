<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<PodcastEpisode>
 */
final class PodcastEpisodeFixtures extends AbstractAssetFileFixtures
{
    public function __construct(
        private readonly PodcastEpisodeManager $podcastManager
    ) {
    }

    public static function getDependencies(): array
    {
        return [
            PodcastFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return PodcastEpisode::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var PodcastEpisode $podcastEpisode */
        foreach ($progressBar->iterate($this->getData()) as $podcastEpisode) {
            $podcastEpisode = $this->podcastManager->create($podcastEpisode);
            $this->addToRegistry($podcastEpisode, $podcastEpisode->getId());
        }
    }

    private function getData(): Generator
    {
        $podcast = $this->entityManager->find(Podcast::class, PodcastFixtures::PODCAST_1);

        yield (new PodcastEpisode())
            ->setTexts(
                (new PodcastEpisodeTexts())
                    ->setTitle('Episode 1')
                    ->setDescription('Episode 1 description')
            )
            ->setPodcast($podcast)
        ;
    }
}
