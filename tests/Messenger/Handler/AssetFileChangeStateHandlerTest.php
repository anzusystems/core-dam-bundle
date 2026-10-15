<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Messenger\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Distribution\ModuleProvider;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusInterface;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoManager;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Handler\AssetFileChangeStateHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\DistributionRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\SerializerBundle\Serializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class AssetFileChangeStateHandlerTest extends CoreDamKernelTestCase
{
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';
    private const string EXAMPLE_FILE_NAME = 'video_example.mp4';
    private const string DOWNLOAD_URL = 'http://core-dam.sme.localhost/download-file?file=video_example.mp4';

    private LoggerInterface&MockObject $journalLogger;
    private JwDistributionFacade&MockObject $jwDistributionFacade;
    private DistributionRepository&MockObject $distributionRepository;
    private MessageBusInterface&MockObject $messageBus;
    private AssetFileStatusInterface&MockObject $statusFacade;
    private DamLogger $damLogger;
    private VideoDownloadFacade $videoDownloadFacade;
    private AssetFileChangeStateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->journalLogger = $this->createMock(LoggerInterface::class);
        $this->jwDistributionFacade = $this->createMock(JwDistributionFacade::class);
        $this->distributionRepository = $this->createMock(DistributionRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->statusFacade = $this->createMock(AssetFileStatusInterface::class);

        $serializer = $this->getService(Serializer::class);
        $this->damLogger = new DamLogger(
            $this->journalLogger,
            new LogContextFactory($this->getService(CurrentAnzuUserProvider::class), $serializer),
        );
        $this->damLogger->setSerializer($serializer);

        $this->videoDownloadFacade = new VideoDownloadFacade(
            $this->getService(VideoFactory::class),
            $this->getService(AssetFactory::class),
            $this->getService(VideoManager::class),
            $this->getService(ImagePreviewFactory::class),
            $this->getService(AssetFileStatusManager::class),
            new VideoDistributionFacade(
                $this->getService(ModuleProvider::class),
                $this->getService(ImageDownloadFacade::class),
                $this->getService(VideoManager::class),
                $this->distributionRepository,
                $this->jwDistributionFacade,
                $this->getService(ResourceLocker::class),
            ),
            $this->getService(VideoFileRepository::class),
            $this->damLogger,
        );
        $this->videoDownloadFacade->setMessageBus($this->messageBus);

        $this->handler = new AssetFileChangeStateHandler(
            $this->getService(AssetFileRepository::class),
            new AssetFileStatusFacadeProvider([VideoFile::class => $this->statusFacade]),
            $this->damLogger,
            $this->getService(FileSystemProvider::class),
            $this->videoDownloadFacade,
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
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testProcessedWithFailingDistributionRethrowsOriginalException(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->expectDistributionAttempt($videoFile, new RuntimeException('jw down'));
        $this->journalLogger->expects(self::never())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jw down');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testProcessedWithRejectedDistributionReportsVideoFailedOnce(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->expectDistributionAttempt($videoFile, new ValidationException());
        $this->journalLogger->expects(self::once())->method('error');
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof VideoMediaStatusCallbackMessage
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoFailed)
                    && VideoMediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED === $message->failureReason
                    && null === $message->originAssetId
                    && null === $message->originAssetFileId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testProcessedWithoutServicesDoesNotStart(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Processed);

        $this->expectNoDistribution();
        $this->journalLogger->expects(self::never())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
    }

    public function testDuplicateDispatchesVideoDuplicateCallbackAndDistributesProcessedOrigin(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $videoFile = $this->presetDuplicateVideoFile((string) $origin->getId());

        $this->expectDistributionAttempt($origin);
        $this->journalLogger->expects(self::never())->method('error');
        $this->expectDuplicateCallback($videoFile, $origin);
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testDuplicateWithRejectedOriginDistributionStillReportsVideoDuplicate(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $videoFile = $this->presetDuplicateVideoFile((string) $origin->getId());

        $this->expectDistributionAttempt($origin, new ValidationException());
        $this->journalLogger->expects(self::once())->method('error');
        $this->expectDuplicateCallback($videoFile, $origin);
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    public function testDuplicateWithFailingDistributionSendsNoCallback(): void
    {
        $origin = $this->presetOrigin(AssetFileProcessStatus::Processed);
        $videoFile = $this->presetDuplicateVideoFile((string) $origin->getId());

        $this->expectDistributionAttempt($origin, new RuntimeException('jw down'));
        $this->journalLogger->expects(self::never())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jw down');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, [self::DISTRIBUTION_SERVICE]));
    }

    #[DataProvider('duplicateWithoutDistributionDataProvider')]
    public function testDuplicateDoesNotDistribute(
        ?AssetFileProcessStatus $originStatus,
        AssetFileCreateStrategy $createStrategy,
        array $services,
    ): void {
        $origin = null === $originStatus ? null : $this->presetOrigin($originStatus);
        $originAssetFileId = null === $origin ? Uuid::v4()->toRfc4122() : (string) $origin->getId();
        $videoFile = $this->presetDuplicateVideoFile($originAssetFileId, $createStrategy);

        $this->expectNoDistribution();
        $this->journalLogger->expects(self::never())->method('error');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');
        $this->expectDuplicateOutcome($videoFile, $origin, $createStrategy);

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile, $services));
    }

    public static function duplicateWithoutDistributionDataProvider(): array
    {
        return [
            'origin processed without services' => [
                'originStatus' => AssetFileProcessStatus::Processed,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'services' => [],
            ],
            'origin not processed with services' => [
                'originStatus' => AssetFileProcessStatus::Uploaded,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'services' => [self::DISTRIBUTION_SERVICE],
            ],
            'origin missing without services' => [
                'originStatus' => null,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'services' => [],
            ],
            'origin missing with services' => [
                'originStatus' => null,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'services' => [self::DISTRIBUTION_SERVICE],
            ],
            'non-download without services' => [
                'originStatus' => AssetFileProcessStatus::Processed,
                'createStrategy' => AssetFileCreateStrategy::Chunk,
                'services' => [],
            ],
            'non-download with services' => [
                'originStatus' => AssetFileProcessStatus::Processed,
                'createStrategy' => AssetFileCreateStrategy::Chunk,
                'services' => [self::DISTRIBUTION_SERVICE],
            ],
        ];
    }

    #[DataProvider('failedDataProvider')]
    public function testFailedDispatchesVideoFailedCallbackOnlyForDownload(
        array $services,
        AssetFileCreateStrategy $createStrategy,
    ): void {
        $videoFile = $this->presetFailedVideoFile($createStrategy);

        $this->journalLogger->expects(self::never())->method('error');
        $this->expectNoDistribution();
        $this->statusFacade->expects(self::never())->method('storeAndProcess');
        $this->expectFailedOutcome($videoFile, $createStrategy);

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

    public function testSecondMessageForAlreadyProcessedFileDoesNotStoreAndProcessAgain(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Uploaded);

        $this->statusFacade->expects(self::once())
            ->method('storeAndProcess')
            ->willReturnCallback(static function (VideoFile $assetFile): VideoFile {
                $assetFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Processed);

                return $assetFile;
            });
        $this->journalLogger->expects(self::never())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
    }

    public function testFailedProcessingLetsRedeliveryProcessFile(): void
    {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Uploaded);
        $attempt = App::ZERO;

        $this->statusFacade->expects(self::exactly(2))
            ->method('storeAndProcess')
            ->willReturnCallback(static function (VideoFile $assetFile) use (&$attempt): VideoFile {
                $attempt++;
                if (1 === $attempt) {
                    throw new RuntimeException('storage_unavailable');
                }
                $assetFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Processed);

                return $assetFile;
            });
        $this->journalLogger->expects(self::once())->method('error');
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');

        try {
            $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));
            self::fail('A failed processing must fail the delivery so Pub/Sub retries it.');
        } catch (RuntimeException $exception) {
            self::assertSame('storage_unavailable', $exception->getMessage());
        }

        $this->handler->handleVideoFile(new VideoFileChangeStateMessage($videoFile));

        self::assertTrue($videoFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testDuplicateCreatedByRealProducerCarriesOriginAssetAndFileIds(): void
    {
        $realHandler = new AssetFileChangeStateHandler(
            $this->getService(AssetFileRepository::class),
            $this->getService(AssetFileStatusFacadeProvider::class),
            $this->damLogger,
            $this->getService(FileSystemProvider::class),
            $this->videoDownloadFacade,
        );
        $origin = $this->createUploadedDownloadVideo('real-producer-origin');
        $duplicate = $this->createUploadedDownloadVideo('real-producer-duplicate');

        $this->expectNoDistribution();
        $this->journalLogger->expects(self::never())->method('error');
        $this->statusFacade->expects(self::never())->method('storeAndProcess');
        $this->expectDuplicateCallback($duplicate, $origin);

        $realHandler->handleVideoFile(new VideoFileChangeStateMessage($origin));
        self::assertTrue($origin->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));

        $realHandler->handleVideoFile(new VideoFileChangeStateMessage($duplicate));
        self::assertTrue($duplicate->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Duplicate));
    }

    private function expectFailedCallback(VideoFile $videoFile): void
    {
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof VideoMediaStatusCallbackMessage
                    && $message->extSystemId === $videoFile->getExtSystem()->getId()
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoFailed)
                    && AssetFileFailedType::DownloadFailed->toString() === $message->failureReason
                    && null === $message->originAssetId
                    && null === $message->originAssetFileId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
    }

    private function expectDuplicateCallback(VideoFile $videoFile, VideoFile $origin): void
    {
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof VideoMediaStatusCallbackMessage
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoDuplicate)
                    && null === $message->failureReason
                    && $message->originAssetId === (string) $origin->getAsset()->getId()
                    && $message->originAssetFileId === (string) $origin->getId()
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
    }

    private function expectNoDistribution(): void
    {
        $this->distributionRepository->expects(self::never())->method('findByAssetFileAndDistributionService');
        $this->jwDistributionFacade->expects(self::never())->method('distribute');
    }

    private function expectDistributionAttempt(VideoFile $distributed, ?Throwable $failure = null): void
    {
        $distribution = new JwDistribution();

        $this->distributionRepository->expects(self::once())
            ->method('findByAssetFileAndDistributionService')
            ->with((string) $distributed->getId(), self::DISTRIBUTION_SERVICE)
            ->willReturn(null);
        $this->jwDistributionFacade->expects(self::once())
            ->method('preparePayload')
            ->with($distributed, self::DISTRIBUTION_SERVICE)
            ->willReturn($distribution);
        $distribute = $this->jwDistributionFacade->expects(self::once())
            ->method('distribute')
            ->with($distributed, $distribution);
        if (null !== $failure) {
            $distribute->willThrowException($failure);
        }
    }

    private function expectDuplicateOutcome(
        VideoFile $videoFile,
        ?VideoFile $origin,
        AssetFileCreateStrategy $createStrategy,
    ): void {
        if ($createStrategy->isNot(AssetFileCreateStrategy::Download)) {
            $this->messageBus->expects(self::never())->method('dispatch');

            return;
        }
        if (null === $origin) {
            $this->expectMissingOriginDuplicateCallback($videoFile);

            return;
        }

        $this->expectDuplicateCallback($videoFile, $origin);
    }

    private function expectFailedOutcome(VideoFile $videoFile, AssetFileCreateStrategy $createStrategy): void
    {
        if ($createStrategy->isNot(AssetFileCreateStrategy::Download)) {
            $this->messageBus->expects(self::never())->method('dispatch');

            return;
        }

        $this->expectFailedCallback($videoFile);
    }

    private function expectMissingOriginDuplicateCallback(VideoFile $videoFile): void
    {
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof VideoMediaStatusCallbackMessage
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoDuplicate)
                    && null === $message->failureReason
                    && null === $message->originAssetId
                    && null === $message->originAssetFileId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
    }

    private function createUploadedDownloadVideo(string $caseKey): VideoFile
    {
        $file = new AdapterFile(
            path: VideoFixtures::DATA_PATH . self::EXAMPLE_FILE_NAME,
            adapterPath: self::EXAMPLE_FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(VideoFixtures::DATA_PATH),
        );
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        self::assertNotNull($licence);
        $videoFile = $this->getService(VideoFactory::class)->createFromFile($file, $licence);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $videoFile->getAssetAttributes()
            ->setStatus(AssetFileProcessStatus::Uploaded)
            ->setCreateStrategy(AssetFileCreateStrategy::Download)
            ->setOriginUrl(self::DOWNLOAD_URL . '&case=' . $caseKey);

        return $videoFile;
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
        string $originAssetFileId,
        AssetFileCreateStrategy $createStrategy = AssetFileCreateStrategy::Download,
    ): VideoFile {
        $videoFile = $this->presetVideoFile(AssetFileProcessStatus::Duplicate, $createStrategy);
        $videoFile->getAssetAttributes()->setOriginAssetId($originAssetFileId);

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
