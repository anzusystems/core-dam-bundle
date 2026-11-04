<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Job\Processor\JobUserDataDeleteProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class JobUserDataDeleteProcessorTest extends CoreDamKernelTestCase
{
    private JobProcessor $jobProcessor;
    private JobUserDataDeleteProcessor $jobUserDataDeleteProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = self::getContainer()->get(JobProcessor::class);
        $this->jobProcessor = $jobProcessor;

        /** @var JobUserDataDeleteProcessor $jobUserDataDeleteProcessor */
        $jobUserDataDeleteProcessor = self::getContainer()->get(JobUserDataDeleteProcessor::class);
        $this->jobUserDataDeleteProcessor = $jobUserDataDeleteProcessor;
    }

    public function testProcess(): void
    {
        $this->jobUserDataDeleteProcessor->setBulkSize(1);

        // 1. Process the first bulk
        $job = $this->entityManager->getRepository(JobUserDataDelete::class)->findAll()[0];
        $this->jobProcessor->process($job);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);

        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(1, $job->getBatchProcessedIterationCount());

        // 2. Process the second bulk
        $this->jobProcessor->process($job);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);
        $this->assertSame(JobStatus::AwaitingBatchProcess, $job->getStatus());
        $this->assertSame(2, $job->getBatchProcessedIterationCount());

        // 3. Process the third bulk
        $this->jobProcessor->process($job);
        $this->assertInstanceOf(JobUserDataDelete::class, $job);
        $this->assertSame(2, $job->getBatchProcessedIterationCount());
        $this->assertSame(JobStatus::Done, $job->getStatus());

        // 4. Check if user anonymized
        $user = $this->entityManager->find(User::class, $job->getTargetUserId());
        $this->assertInstanceOf(User::class, $user);
        $this->assertCount(0, $user->getAssetLicences());
        $this->assertStringStartsWith('deleted_', $user->getEmail());

        // 5. Check if licence for blog deleted but cms kept untouched
        $blogLicence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        $this->assertNull($blogLicence);
        $cmsLicence = $this->entityManager->find(AssetLicence::class, BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);
        $this->assertInstanceOf(AssetLicence::class, $cmsLicence);
        /** @var AssetRepository $assetRepository */
        $assetRepository = self::getContainer()->get(AssetRepository::class);
        $assets = $assetRepository->geAllByLicenceIds([BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID], 1);
        $this->assertNotEmpty($assets);
    }

    public function testAnonymizationDeletesALicenceAListViewUploadsIntoAndUnsetsTheUploadLicence(): void
    {
        $licence = $this->createLicenceWithTheCmsUserAsOnlyMember();
        $licenceId = $licence->getId();
        $viewId = $this->createListViewUploadingInto($licence)->getId();
        $job = $this->getService(JobManager::class)->create(
            (new JobUserDataDelete())->setTargetUserId(User::ID_CMS_USER)->setAnonymizeUser(true)
        );

        $this->jobProcessor->process($job);
        $this->entityManager->clear();

        self::assertSame(JobStatus::Done, $this->entityManager->find(JobUserDataDelete::class, $job->getId())?->getStatus());
        self::assertNull($this->entityManager->find(AssetLicence::class, $licenceId));
        $view = $this->entityManager->find(AssetListView::class, $viewId);
        self::assertInstanceOf(AssetListView::class, $view);
        self::assertNull($view->getUploadLicence());
    }

    private function createLicenceWithTheCmsUserAsOnlyMember(): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = $this->getService(AssetLicenceManager::class)->create(
            (new AssetLicence())->setExtSystem($extSystem)->setExtId('cms-user-only-licence')
        );
        /** @var User $user */
        $user = $this->entityManager->find(User::class, User::ID_CMS_USER);
        $user->getAssetLicences()->add($licence);
        $licence->getUsers()->add($user);
        $this->entityManager->flush();

        return $licence;
    }

    private function createListViewUploadingInto(AssetLicence $licence): AssetListView
    {
        /** @var User $admin */
        $admin = $this->entityManager->find(User::class, User::ID_ADMIN);
        $view = (new AssetListView())
            ->setName('View uploading into the licence')
            ->setExtSystem($licence->getExtSystem())
            ->setLicences(new ArrayCollection([$licence]))
            ->setUploadLicence($licence)
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($admin)
            ->setModifiedBy($admin)
        ;
        $this->entityManager->persist($view);
        $this->entityManager->flush();

        return $view;
    }
}
