<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode\VideoShowEpisodeManager;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeFlags;
use AnzuSystems\CoreDamBundle\Entity\Embeds\VideoShowEpisodeTexts;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<VideoShowEpisode>
 */
final class VideoShowEpisodeFixtures extends AbstractAssetFileFixtures
{
    public const string EPISODE_1 = 'a081fd6f-57a0-4c9c-8a98-0347499b8ae2';
    public const string EPISODE_2 = '6b27f43c-530a-403f-9aa5-d728d825db2f';

    public function __construct(
        private readonly VideoShowEpisodeManager $videoShowEpisodeManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['dev', 'test'];
    }

    public static function getDependencies(): array
    {
        return [
            VideoShowFixtures::class,
        ];
    }

    public static function getIndexKey(): string
    {
        return VideoShowEpisode::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var VideoShowEpisode $videoShowEpisode */
        foreach ($progressBar->iterate($this->getData()) as $videoShowEpisode) {
            $videoShowEpisode = $this->videoShowEpisodeManager->create($videoShowEpisode);
            $this->addToRegistry($videoShowEpisode, $videoShowEpisode->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var VideoShow $show */
        $show = $this->entityManager->find(VideoShow::class, VideoShowFixtures::SHOW_1);
        /** @var VideoFile $videoFile */
        $videoFile = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);

        yield (new VideoShowEpisode())
            ->setId(self::EPISODE_1)
            ->setTexts(
                (new VideoShowEpisodeTexts())
                    ->setTitle('Episode with asset')
            )
            ->setAttributes(
                (new VideoShowEpisodeAttributes())
                    ->setMobileOrderPosition(100)
                    ->setWebOrderPosition(100)
            )
            ->setFlags(
                (new VideoShowEpisodeFlags())
                    ->setMobilePublicExportEnabled(true)
                    ->setWebPublicExportEnabled(true)
            )
            ->setVideoShow($show)
            ->setAsset($videoFile->getAsset());

        yield (new VideoShowEpisode())
            ->setId(self::EPISODE_2)
            ->setTexts(
                (new VideoShowEpisodeTexts())
                    ->setTitle('Episode without asset')
            )
            ->setAttributes(
                (new VideoShowEpisodeAttributes())
                    ->setMobileOrderPosition(100)
                    ->setWebOrderPosition(100)
            )
            ->setFlags(
                (new VideoShowEpisodeFlags())
                    ->setMobilePublicExportEnabled(true)
                    ->setWebPublicExportEnabled(true)
            )
            ->setVideoShow($show);
    }
}
