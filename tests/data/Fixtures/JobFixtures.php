<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CoreDamBundle\DataFixtures\PodcastFixtures;
use AnzuSystems\CoreDamBundle\Entity\JobAssetFileReprocessInternalFlag;
use AnzuSystems\CoreDamBundle\Entity\JobPodcastSynchronizer;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Job>
 */
final class JobFixtures extends AbstractFixtures
{
    public function __construct(
        private readonly JobManager $jobManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return JobPodcastSynchronizer::class;
    }

    public static function getDependencies(): array
    {
        return [
            UserFixtures::class,
            AssetLicenceFixtures::class,
        ];
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var Job $job */
        foreach ($progressBar->iterate($this->getData()) as $job) {
            $this->jobManager->create($job);
        }
    }

    private function getData(): Generator
    {
        yield (new JobUserDataDelete())
            ->setTargetUserId(User::ID_BLOG_USER)
            ->setAnonymizeUser(true)
        ;

        yield (new JobPodcastSynchronizer())
            ->setFullSync(true)
        ;

        yield (new JobPodcastSynchronizer())
            ->setFullSync(false)
            ->setPodcastId(PodcastFixtures::PODCAST_1)
        ;

        yield (new JobAssetFileReprocessInternalFlag())
            ->setTargetLicenceId(AssetLicenceFixtures::LICENCE_ID)
        ;
    }
}
