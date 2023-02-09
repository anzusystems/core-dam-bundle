<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\PodcastEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<PodcastEpisode>
 */
final class PodcastEpisodeFixtures extends AbstractFixtures
{
    public const EPISODE_1_ID = '84a9f83c-8d3f-4800-a32e-2b61b7f5875e';
    public const EPISODE_2_ID = 'ba87ee36-6312-4dd6-a2e0-9572ac6dad90';

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

    public function useCustomId(): bool
    {
        return true;
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
        $audio = $this->entityManager->find(AudioFile::class, AudioFixtures::AUDIO_ID_1);

        yield (new PodcastEpisode())
            ->setId(self::EPISODE_1_ID)
            ->setAsset($audio->getAsset())
            ->setAttributes(
                (new PodcastEpisodeAttributes())
                    ->setRssUrl('http://core.dam.localhost/rssurl')
                    ->setRssId('123')
            )
            ->setTexts(
                (new PodcastEpisodeTexts())
                    ->setTitle('Episode 1')
                    ->setDescription('Episode 1 description')
            )
            ->setPodcast($podcast);

        yield (new PodcastEpisode())
            ->setId(self::EPISODE_2_ID)
            ->setTexts(
                (new PodcastEpisodeTexts())
                    ->setTitle('Episode 2')
                    ->setDescription('Episode 2 description')
            )
            ->setPodcast($podcast);
    }
}
