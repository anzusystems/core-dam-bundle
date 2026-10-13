<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Messenger\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusInterface;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoManager;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Handler\AssetFileChangeStateHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class AssetFileChangeStateHandlerTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';

    private LoggerInterface&MockObject $journalLogger;
    private JwDistributionFacade&MockObject $jwDistributionFacade;
    private DistributionRepository&MockObject $distributionRepository;
    private MessageBusInterface&MockObject $messageBus;
    private AssetFileChangeStateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journalLogger = $this->createMock(LoggerInterface::class);
        $this->jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $this->distributionRepository = $this->createMock(DistributionRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

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
            new VideoDistributionFacade(
                $this->getService(ModuleProvider::class),
                $this->getService(ImageDownloadFacade::class),
                $this->getService(VideoManager::class),
                $this->distributionRepository,
                $this->jwDistributionFacade,
                $this->getService(ResourceLocker::class),
            ),
            $this->getService(AssetRepository::class),
        );
        $this->handler->setMessageBus($this->messageBus);
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
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testProcessedWithFailingDistributionLogsErrorAndRethrows(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->distributionRepository->expects(self::once())
            ->method('findByAssetFileAndDistributionService')
            ->willReturn(null);
        $this->jwDistributionFacade->expects(self::once())->method('preparePayload')->willReturn(new JwDistribution());
        $this->jwDistributionFacade->expects(self::once())
            ->method('distribute')
            ->willThrowException(new Exception('jw down'));
        $this->journalLogger->expects(self::once())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->expectException(RuntimeException::class);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    #[DataProvider('failedDataProvider')]
    public function testFailedDispatchesVideoFailedCallbackOnlyForDownload(
        array $services,
        AssetFileCreateStrategy $createStrategy,
    ): void {
        $videoFile = $this->presetFailedVideoFile($createStrategy);
        $this->journalLogger->expects([] === $services ? self::never() : self::once())->method('error');

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->expectFailedCallback($videoFile, $createStrategy);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, $services));
    }

    public static function failedDataProvider(): array
    {
        return [
            'download without services' => [
                'services' => [],
                'createStrategy' => AssetFileCreateStrategy::Download,
            ],
            'download with services' => [
                'services' => [self::DISTRIBUTION_SERVICE],
                'createStrategy' => AssetFileCreateStrategy::Download,
            ],
            'chunk without services' => [
                'services' => [],
                'createStrategy' => AssetFileCreateStrategy::Chunk,
            ],
            'chunk with services' => [
                'services' => [self::DISTRIBUTION_SERVICE],
                'createStrategy' => AssetFileCreateStrategy::Chunk,
            ],
        ];
    }

    public function testDuplicateDispatchesVideoDuplicateCallbackAndDistributesProcessedOrigin(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $originAssetId = (string) $origin->getAsset()->getId();
        $videoFile = $this->presetDuplicateVideoFile($originAssetId);
        $this->journalLogger->expects(self::never())->method('error');
        $distribution = new JwDistribution();

        $this->distributionRepository->expects(self::once())
            ->method('findByAssetFileAndDistributionService')
            ->with((string) $origin->getId(), self::DISTRIBUTION_SERVICE)
            ->willReturn(null);
        $this->jwDistributionFacade->expects(self::once())
            ->method('preparePayload')
            ->with($origin, self::DISTRIBUTION_SERVICE)
            ->willReturn($distribution);
        $this->jwDistributionFacade->expects(self::once())
            ->method('distribute')
            ->with($origin, $distribution);
        $this->expectDuplicateCallback($videoFile, $originAssetId);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testDuplicateWithoutServicesOnlyDispatchesCallback(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $originAssetId = (string) $origin->getAsset()->getId();
        $videoFile = $this->presetDuplicateVideoFile($originAssetId);
        $this->journalLogger->expects(self::never())->method('error');

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->expectDuplicateCallback($videoFile, $originAssetId);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
    }

    public function testDuplicateWithNotProcessedOriginDoesNotDistribute(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Uploaded);
        $originAssetId = (string) $origin->getAsset()->getId();
        $videoFile = $this->presetDuplicateVideoFile($originAssetId);
        $this->journalLogger->expects(self::once())->method('error');

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->expectDuplicateCallback($videoFile, $originAssetId);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    #[DataProvider('servicesDataProvider')]
    public function testDuplicateWithMissingOriginAssetDoesNotDistribute(array $services): void
    {
        $originAssetId = Uuid::v4()->toRfc4122();
        $videoFile = $this->presetDuplicateVideoFile($originAssetId);
        $this->journalLogger->expects([] === $services ? self::never() : self::once())->method('error');

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->expectDuplicateCallback($videoFile, $originAssetId);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, $services));
    }

    public static function servicesDataProvider(): array
    {
        return [
            'without services' => ['services' => []],
            'with services' => ['services' => [self::DISTRIBUTION_SERVICE]],
        ];
    }

    #[DataProvider('servicesDataProvider')]
    public function testDuplicateOfNonDownloadDispatchesNothingAndDoesNotDistribute(array $services): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $videoFile = $this->presetDuplicateVideoFile(
            (string) $origin->getAsset()->getId(),
            AssetFileCreateStrategy::Chunk
        );
        $this->journalLogger->expects([] === $services ? self::never() : self::once())->method('error');

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, $services));
    }

    public function testMessageWithoutServicesDoesNotStart(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
        $this->journalLogger->expects(self::never())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
    }

    private function expectFailedCallback(VideoFile $videoFile, AssetFileCreateStrategy $createStrategy): void
    {
        if ($createStrategy->isNot(AssetFileCreateStrategy::Download)) {
            $this->messageBus->expects(self::never())->method('dispatch');

            return;
        }

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof MediaStatusCallbackMessage
                    && $message->extSystemId === $videoFile->getExtSystem()->getId()
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && MediaStatusType::VideoFailed === $message->status
                    && AssetFileFailedType::DownloadFailed->toString() === $message->failureReason
                    && null === $message->originAssetId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
    }

    private function expectDuplicateCallback(VideoFile $videoFile, string $originAssetId): void
    {
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof MediaStatusCallbackMessage
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && MediaStatusType::VideoDuplicate === $message->status
                    && null === $message->failureReason
                    && $originAssetId === $message->originAssetId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
    }

    private function presetOrigin(AssetFileProcessStatus $status): VideoFile
    {
        $origin = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_2);
        self::assertInstanceOf(VideoFile::class, $origin);
        self::assertSame($origin, $origin->getAsset()->getMainFile());
        $origin->getAssetAttributes()->setStatus($status);

        return $origin;
    }

    private function presetDuplicateVideoFile(
        string $originAssetId,
        AssetFileCreateStrategy $createStrategy = AssetFileCreateStrategy::Download,
    ): VideoFile {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Duplicate, $createStrategy);
        $videoFile->getAssetAttributes()->setOriginAssetId($originAssetId);

        return $videoFile;
    }

    private function presetFailedVideoFile(AssetFileCreateStrategy $createStrategy): VideoFile
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Failed, $createStrategy);
        $videoFile->getAssetAttributes()->setFailReason(AssetFileFailedType::DownloadFailed);

        return $videoFile;
    }

    private function presetVideoFile(
        AssetFileProcessStatus $status,
        AssetFileCreateStrategy $createStrategy = AssetFileCreateStrategy::Download,
    ): VideoFile {
        $videoFile = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $videoFile->getAssetAttributes()->setStatus($status)->setCreateStrategy($createStrategy);

        return $videoFile;
    }
}
