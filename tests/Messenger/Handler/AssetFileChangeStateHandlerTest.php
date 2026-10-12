<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Messenger\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Distribution\DistributionBroker;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusInterface;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionStarter;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Handler\AssetFileChangeStateHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class AssetFileChangeStateHandlerTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';

    private LoggerInterface&MockObject $journalLogger;
    private JwDistributionFacade&MockObject $jwDistributionFacade;
    private DistributionRepository&MockObject $distributionRepository;
    private AssetFileChangeStateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journalLogger = $this->createMock(LoggerInterface::class);
        $this->jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $this->distributionRepository = $this->createMock(DistributionRepository::class);

        $serializer = $this->getService(Serializer::class);
        $damLogger = new DamLogger(
            $this->journalLogger,
            new LogContextFactory($this->getService(CurrentAnzuUserProvider::class), $serializer),
        );
        $damLogger->setSerializer($serializer);

        $this->handler = new AssetFileChangeStateHandler(
            $this->getService(AssetFileRepository::class),
            new AssetFileStatusFacadeProvider([VideoFile::class => $this->createStub(AssetFileStatusInterface::class)]),
            $damLogger,
            $this->getService(FileSystemProvider::class),
            new VideoDistributionStarter(
                $this->jwDistributionFacade,
                $this->distributionRepository,
                (new ReflectionClass(DistributionBroker::class))->newInstanceWithoutConstructor(),
            ),
        );
    }

    public function testProcessedWithServicesStartsDistributionOnce(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);
        $distribution = new JwDistribution();

        $this->distributionRepository->expects(self::once())
            ->method('findByAssetFileAndDistributionService')
            ->with($videoFile->getId(), self::DISTRIBUTION_SERVICE)
            ->willReturn(null);
        $this->jwDistributionFacade->expects(self::once())
            ->method('preparePayload')
            ->with($videoFile, self::DISTRIBUTION_SERVICE)
            ->willReturn($distribution);
        $this->jwDistributionFacade->expects(self::once())
            ->method('distribute')
            ->with($videoFile, $distribution);
        $this->journalLogger->expects(self::never())->method('error');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    #[DataProvider('notStartedStatusDataProvider')]
    public function testFailedOrDuplicateWithServicesLogsErrorAndDoesNotStart(AssetFileProcessStatus $status): void
    {
        $videoFile = $this->presetVideoFile($status);

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->journalLogger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('not started'));

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public static function notStartedStatusDataProvider(): array
    {
        return [
            'failed' => ['status' => AssetFileProcessStatus::Failed],
            'duplicate' => ['status' => AssetFileProcessStatus::Duplicate],
        ];
    }

    public function testMessageWithoutServicesDoesNotStart(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->journalLogger->expects(self::never())->method('error');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
    }

    private function presetVideoFile(AssetFileProcessStatus $status): VideoFile
    {
        $videoFile = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $videoFile->getAssetAttributes()->setStatus($status);

        return $videoFile;
    }
}
