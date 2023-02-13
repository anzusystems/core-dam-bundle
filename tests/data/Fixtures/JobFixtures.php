<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<Job>
 */
final class JobFixtures extends AbstractFixtures
{
    public const ID_DELETE_BLOG_USER_JOB = 1;

    public function __construct(
        private readonly JobManager $jobManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return Job::class;
    }

    public static function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function useCustomId(): bool
    {
        return true;
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
            ->setId(self::ID_DELETE_BLOG_USER_JOB)
            ->setTargetUserId(User::ID_BLOG_USER)
            ->setAnonymizeUser(true)
        ;
    }
}
