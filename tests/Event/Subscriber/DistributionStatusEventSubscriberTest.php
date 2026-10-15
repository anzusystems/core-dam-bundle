<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Event\Subscriber;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionManagerProvider;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Event\Subscriber\DistributionStatusEventSubscriber;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DistributionStatusEventSubscriberTest extends CoreDamKernelTestCase
{
    private const string JW_EXT_ID = 'jw-media-1';
    private const string DISTRIBUTION_SERVICE = 'jw_cms_main';
    private const string ORIGIN_URL = 'http://core-dam.sme.localhost/download-file?file=video_example.mp4';

    private DistributionStatusEventSubscriber $subscriber;
    private MessageBusInterface&MockObject $messageBus;
    private VideoFile $videoFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->subscriber = $this->getService(DistributionStatusEventSubscriber::class);
        $this->subscriber->setMessageBus($this->messageBus);
        $videoFile = $this->getService(VideoFileRepository::class)->find(VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $this->videoFile = $videoFile;
    }

    #[DataProvider('jwDistributionStatusDataProvider')]
    public function testJwDistributionStatusDispatchesVideoFailedOnlyForFirstFailureOfDownloadedVideo(
        DistributionProcessStatus $status,
        AssetFileCreateStrategy $createStrategy,
        ?string $originUrl,
        string $extId,
        bool $dispatches,
    ): void {
        $this->prepareVideoFile($createStrategy, $originUrl);
        $videoFile = $this->videoFile;

        $this->messageBus->expects($dispatches ? self::once() : self::never())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof MediaStatusCallbackMessage
                    && $message->extSystemId === $videoFile->getExtSystem()->getId()
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoFailed)
                    && MediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED === $message->failureReason
                    && null === $message->originAssetId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($this->createDistribution($this->presetJwDistribution($extId)), $status)
        );
    }

    public static function jwDistributionStatusDataProvider(): array
    {
        return [
            'failed download with origin url' => [
                'status' => DistributionProcessStatus::Failed,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'originUrl' => self::ORIGIN_URL,
                'extId' => App::EMPTY_STRING,
                'dispatches' => true,
            ],
            'failed download without origin url' => [
                'status' => DistributionProcessStatus::Failed,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'originUrl' => null,
                'extId' => App::EMPTY_STRING,
                'dispatches' => true,
            ],
            'failed chunk with origin url' => [
                'status' => DistributionProcessStatus::Failed,
                'createStrategy' => AssetFileCreateStrategy::Chunk,
                'originUrl' => self::ORIGIN_URL,
                'extId' => App::EMPTY_STRING,
                'dispatches' => false,
            ],
            'failed download already on jw' => [
                'status' => DistributionProcessStatus::Failed,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'originUrl' => self::ORIGIN_URL,
                'extId' => self::JW_EXT_ID,
                'dispatches' => false,
            ],
            'distributed download' => [
                'status' => DistributionProcessStatus::Distributed,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'originUrl' => self::ORIGIN_URL,
                'extId' => App::EMPTY_STRING,
                'dispatches' => false,
            ],
            'distributing download' => [
                'status' => DistributionProcessStatus::Distributing,
                'createStrategy' => AssetFileCreateStrategy::Download,
                'originUrl' => self::ORIGIN_URL,
                'extId' => App::EMPTY_STRING,
                'dispatches' => false,
            ],
        ];
    }

    public function testDistributionStatusFacadeToFailedDispatchesVideoFailedForDownloadedVideoWithoutExtId(): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Download, self::ORIGIN_URL);
        $videoFile = $this->videoFile;
        $distribution = $this->getService(JwDistributionFacade::class)
            ->preparePayload($videoFile, self::DISTRIBUTION_SERVICE);
        $this->getService(DistributionManagerProvider::class)->get(JwDistribution::class)->create($distribution);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof MediaStatusCallbackMessage
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && $message->status->is(MediaStatusType::VideoFailed)
                    && MediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED === $message->failureReason
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->getService(DistributionStatusFacade::class)->toFailed($distribution);
    }

    #[DataProvider('notVideoFileDataProvider')]
    public function testFailedJwDistributionOfNonVideoOrMissingAssetFileDispatchesNothing(bool $withImageFile): void
    {
        $distribution = new JwDistribution();
        if ($withImageFile) {
            $imageFile = $this->getService(ImageFileRepository::class)->find(ImageFixtures::IMAGE_ID_1_1);
            self::assertInstanceOf(ImageFile::class, $imageFile);
            $imageFile->getAssetAttributes()->setCreateStrategy(AssetFileCreateStrategy::Download);
            $distribution->setAssetFile($imageFile);
        }

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($distribution, DistributionProcessStatus::Failed)
        );
    }

    public static function notVideoFileDataProvider(): array
    {
        return [
            'image file with download strategy' => [
                'withImageFile' => true,
            ],
            'no asset file' => [
                'withImageFile' => false,
            ],
        ];
    }

    public function testFailedNonJwDistributionDispatchesNothing(): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Download, self::ORIGIN_URL);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent(
                $this->createDistribution(new YoutubeDistribution()),
                DistributionProcessStatus::Failed
            )
        );
    }

    private function prepareVideoFile(AssetFileCreateStrategy $createStrategy, ?string $originUrl): void
    {
        $this->videoFile->getAssetAttributes()->setCreateStrategy($createStrategy)->setOriginUrl($originUrl);
    }

    private function presetJwDistribution(string $extId): JwDistribution
    {
        return (new JwDistribution())->setExtId($extId);
    }

    private function createDistribution(Distribution $distribution): Distribution
    {
        return $distribution->setAssetFile($this->videoFile);
    }
}
