<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Video;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionStarter;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Messenger\Message\DistributeMessage;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class VideoDistributionStarterTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';

    private VideoDistributionStarter $starter;
    private DistributionBroker $broker;
    private DistributionRepository $distributionRepository;
    private VideoFile $videoFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = $this->getService(DistributionBroker::class);
        $this->distributionRepository = $this->getService(DistributionRepository::class);
        $this->starter = new VideoDistributionStarter(
            $this->getService(JwDistributionFacade::class),
            $this->distributionRepository,
            $this->broker,
        );
        $videoFile = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $this->videoFile = $videoFile;
    }

    public function testStartWithoutTokenCreatesDistributionAsAnonymousUser(): void
    {
        $this->broker->setMessageBus($this->createBus());

        $this->starter->start($this->videoFile, [self::DISTRIBUTION_SERVICE]);

        $distribution = $this->distributionRepository->findByAssetFileAndDistributionService(
            (string) $this->videoFile->getId(),
            self::DISTRIBUTION_SERVICE
        );
        self::assertInstanceOf(JwDistribution::class, $distribution);
        self::assertSame(AnzuApp::getUserIdAnonymous(), $distribution->getCreatedBy()->getId());
        self::assertSame(AnzuApp::getUserIdAnonymous(), $distribution->getModifiedBy()->getId());
    }

    public function testStartWithExistingDistributionOnlyStartsIt(): void
    {
        $this->broker->setMessageBus($this->createBus());
        $this->starter->start($this->videoFile, [self::DISTRIBUTION_SERVICE]);
        $existing = $this->distributionRepository->findByAssetFileAndDistributionService(
            (string) $this->videoFile->getId(),
            self::DISTRIBUTION_SERVICE
        );
        self::assertInstanceOf(JwDistribution::class, $existing);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof DistributeMessage
                    && $message->getDistributionId() === $existing->getId()
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $this->broker->setMessageBus($messageBus);

        $this->starter->start($this->videoFile, [self::DISTRIBUTION_SERVICE]);

        self::assertCount(1, $this->distributionRepository->findByAssetFile((string) $this->videoFile->getId()));
    }

    public function testStartSkipsExistingDistributionNotWaiting(): void
    {
        $this->broker->setMessageBus($this->createBus());
        $this->starter->start($this->videoFile, [self::DISTRIBUTION_SERVICE]);
        $existing = $this->distributionRepository->findByAssetFileAndDistributionService(
            (string) $this->videoFile->getId(),
            self::DISTRIBUTION_SERVICE
        );
        self::assertInstanceOf(JwDistribution::class, $existing);
        $this->getService(DistributionStatusFacade::class)->toDistributing($existing);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');
        $this->broker->setMessageBus($messageBus);

        $this->starter->start($this->videoFile, [self::DISTRIBUTION_SERVICE]);

        self::assertCount(1, $this->distributionRepository->findByAssetFile((string) $this->videoFile->getId()));
    }

    private function createBus(): MessageBusInterface
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        return $messageBus;
    }
}
