<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Video;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoManager;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoDistributionFacadeTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';
    private const string OTHER_DISTRIBUTION_SERVICE = 'jw_cms_other';

    private VideoDistributionFacade $facade;
    private DistributionBroker $broker;
    private DistributionRepository $distributionRepository;
    private VideoFile $videoFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = $this->getService(DistributionBroker::class);
        $this->distributionRepository = $this->getService(DistributionRepository::class);
        $this->facade = $this->getService(VideoDistributionFacade::class);
        $videoFile = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $this->videoFile = $videoFile;
    }

    public function testEnsureDistributedWithoutTokenCreatesDistributionAsAnonymousUser(): void
    {
        $this->broker->setMessageBus($this->createBus());

        $this->facade->ensureDistributed($this->videoFile, [self::DISTRIBUTION_SERVICE]);

        $distribution = $this->findDistribution();
        self::assertInstanceOf(JwDistribution::class, $distribution);
        self::assertSame(AnzuApp::getUserIdAnonymous(), $distribution->getCreatedBy()->getId());
        self::assertSame(AnzuApp::getUserIdAnonymous(), $distribution->getModifiedBy()->getId());
    }

    #[DataProvider('existingStatusDataProvider')]
    public function testEnsureDistributedWithExistingDistributionDoesNothing(DistributionProcessStatus $status): void
    {
        $this->prepareExistingDistribution($status);
        $this->broker->setMessageBus($this->createNeverDispatchedBus());

        $this->facade->ensureDistributed($this->videoFile, [self::DISTRIBUTION_SERVICE]);

        self::assertCount(1, $this->distributionRepository->findByAssetFile((string) $this->videoFile->getId()));
    }

    public static function existingStatusDataProvider(): array
    {
        return [
            'waiting' => ['status' => DistributionProcessStatus::Waiting],
            'distributing' => ['status' => DistributionProcessStatus::Distributing],
        ];
    }

    public function testEnsureDistributedReleasesLockWhenDistributeFails(): void
    {
        $distributionRepository = $this->createMock(DistributionRepository::class);
        $distributionRepository->expects(self::once())
            ->method('findByAssetFileAndDistributionService')
            ->willReturn(null);
        $jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $jwDistributionFacade->expects(self::once())->method('preparePayload')->willReturn(new JwDistribution());
        $jwDistributionFacade->expects(self::once())
            ->method('distribute')
            ->willThrowException(new RuntimeException('jw down'));
        $facade = $this->createFacade($distributionRepository, $jwDistributionFacade);

        try {
            $facade->ensureDistributed($this->videoFile, [self::DISTRIBUTION_SERVICE]);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException) {
        }

        $resourceLocker = $this->getService(ResourceLocker::class);
        $lockName = 'video_distribution_' . $this->videoFile->getId() . '_' . self::DISTRIBUTION_SERVICE;
        self::assertTrue($resourceLocker->lock($lockName, blocking: false));
        $resourceLocker->unLock($lockName);
    }

    public function testEnsureDistributedStartsOnlyMissingServices(): void
    {
        $distributionRepository = $this->createMock(DistributionRepository::class);
        $distributionRepository->expects(self::exactly(2))
            ->method('findByAssetFileAndDistributionService')
            ->willReturnCallback(
                static fn (string $assetFileId, string $service): ?JwDistribution => self::DISTRIBUTION_SERVICE === $service
                    ? new JwDistribution()
                    : null
            );
        $jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $payload = new JwDistribution();
        $jwDistributionFacade->expects(self::once())
            ->method('preparePayload')
            ->with($this->videoFile, self::OTHER_DISTRIBUTION_SERVICE)
            ->willReturn($payload);
        $jwDistributionFacade->expects(self::once())->method('distribute')->with($this->videoFile, $payload);

        $this->createFacade($distributionRepository, $jwDistributionFacade)
            ->ensureDistributed($this->videoFile, [self::DISTRIBUTION_SERVICE, self::OTHER_DISTRIBUTION_SERVICE]);
    }

    public function testEnsureDistributedWithoutServicesDoesNothing(): void
    {
        $distributionRepository = $this->createMock(DistributionRepository::class);
        $distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $jwDistributionFacade->expects(self::never())->method('distribute');

        $this->createFacade($distributionRepository, $jwDistributionFacade)->ensureDistributed($this->videoFile, []);
    }

    private function prepareExistingDistribution(DistributionProcessStatus $status): void
    {
        $this->broker->setMessageBus($this->createBus());
        $this->facade->ensureDistributed($this->videoFile, [self::DISTRIBUTION_SERVICE]);
        $existing = $this->findDistribution();
        self::assertInstanceOf(JwDistribution::class, $existing);
        if ($status->is(DistributionProcessStatus::Distributing)) {
            $this->getService(DistributionStatusFacade::class)->toDistributing($existing);
        }
    }

    private function createFacade(
        DistributionRepository $distributionRepository,
        JwDistributionFacade $jwDistributionFacade,
    ): VideoDistributionFacade {
        return new VideoDistributionFacade(
            $this->getService(ModuleProvider::class),
            $this->getService(ImageDownloadFacade::class),
            $this->getService(VideoManager::class),
            $distributionRepository,
            $jwDistributionFacade,
            $this->getService(ResourceLocker::class),
        );
    }

    private function findDistribution(): ?object
    {
        return $this->distributionRepository->findByAssetFileAndDistributionService(
            (string) $this->videoFile->getId(),
            self::DISTRIBUTION_SERVICE
        );
    }

    private function createBus(): MessageBusInterface
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        return $messageBus;
    }

    private function createNeverDispatchedBus(): MessageBusInterface
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        return $messageBus;
    }
}
