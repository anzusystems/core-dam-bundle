<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Event\Subscriber;

use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Event\DistributionStatusEvent;
use AnzuSystems\CoreDamBundle\Event\Subscriber\DistributionStatusEventSubscriber;
use AnzuSystems\CoreDamBundle\Messenger\Message\MediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;
use AnzuSystems\CoreDamBundle\Model\Enum\DistributionProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DistributionStatusEventSubscriberTest extends CoreDamKernelTestCase
{
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

    public function testFailedJwDistributionOfDownloadedVideoDispatchesVideoFailedCallback(): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Download, self::ORIGIN_URL);
        $videoFile = $this->videoFile;

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (object $message): bool => $message instanceof MediaStatusCallbackMessage
                    && $message->extSystemId === $videoFile->getExtSystem()->getId()
                    && $message->assetId === (string) $videoFile->getAsset()->getId()
                    && MediaStatusType::VideoFailed === $message->status
                    && MediaStatusCallbackMessage::FAILURE_REASON_DISTRIBUTION_FAILED === $message->failureReason
                    && null === $message->originAssetId
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($this->createDistribution(new JwDistribution()), DistributionProcessStatus::Failed)
        );
    }

    public function testFailedJwDistributionOfDownloadedVideoWithoutOriginUrlDispatchesVideoFailedCallback(): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Download, null);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($this->createDistribution(new JwDistribution()), DistributionProcessStatus::Failed)
        );
    }

    public function testFailedJwDistributionOfNonDownloadedVideoWithOriginUrlDispatchesNothing(): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Chunk, self::ORIGIN_URL);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($this->createDistribution(new JwDistribution()), DistributionProcessStatus::Failed)
        );
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

    #[DataProvider('notFailedStatusDataProvider')]
    public function testNotFailedJwDistributionDispatchesNothing(DistributionProcessStatus $status): void
    {
        $this->prepareVideoFile(AssetFileCreateStrategy::Download, self::ORIGIN_URL);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->subscriber->distributionStatusEvent(
            new DistributionStatusEvent($this->createDistribution(new JwDistribution()), $status)
        );
    }

    public static function notFailedStatusDataProvider(): array
    {
        return [
            'distributed' => [
                'status' => DistributionProcessStatus::Distributed,
            ],
            'distributing' => [
                'status' => DistributionProcessStatus::Distributing,
            ],
        ];
    }

    private function prepareVideoFile(AssetFileCreateStrategy $createStrategy, ?string $originUrl): void
    {
        $this->videoFile->getAssetAttributes()->setCreateStrategy($createStrategy)->setOriginUrl($originUrl);
    }

    private function createDistribution(Distribution $distribution): Distribution
    {
        return $distribution->setAssetFile($this->videoFile);
    }
}
