<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Messenger\Handler;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Messenger\Handler\VideoMediaStatusCallbackHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\VideoMediaStatusCallbackMessage;
use AnzuSystems\CoreDamBundle\Model\Enum\MediaStatusType;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class VideoMediaStatusCallbackHandlerTest extends CoreDamKernelTestCase
{
    private const string ASSET_ID = 'asset-1';
    private const string FAILURE_REASON = 'download_failed';
    private const string ORIGIN_ASSET_ID = 'origin-asset-1';
    private const string ORIGIN_ASSET_FILE_ID = 'origin-file-1';

    #[DataProvider('messageDataProvider')]
    public function testPassesAllMessageFieldsToCallback(
        MediaStatusType $status,
        ?string $failureReason,
        ?string $originAssetId,
        ?string $originAssetFileId,
    ): void {
        $extSystem = $this->getService(ExtSystemRepository::class)->find(ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $extSystem);
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::once())
            ->method('notifyMediaStatus')
            ->with(self::ASSET_ID, $status, $failureReason, $originAssetId, $originAssetFileId);
        $handler = new VideoMediaStatusCallbackHandler(new ExtSystemCallbackFacade(
            new ServiceLocator([
                $extSystem->getSlug() => static fn (): ExtSystemCallbackInterface => $callback,
            ]),
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        ));

        $handler(new VideoMediaStatusCallbackMessage(
            extSystemId: ExtSystemFixtures::ID_CMS,
            assetId: self::ASSET_ID,
            status: $status,
            failureReason: $failureReason,
            originAssetId: $originAssetId,
            originAssetFileId: $originAssetFileId,
        ));
    }

    public static function messageDataProvider(): array
    {
        return [
            'duplicate with origin' => [
                'status' => MediaStatusType::VideoDuplicate,
                'failureReason' => null,
                'originAssetId' => self::ORIGIN_ASSET_ID,
                'originAssetFileId' => self::ORIGIN_ASSET_FILE_ID,
            ],
            'duplicate with missing origin' => [
                'status' => MediaStatusType::VideoDuplicate,
                'failureReason' => null,
                'originAssetId' => null,
                'originAssetFileId' => self::ORIGIN_ASSET_FILE_ID,
            ],
            'failed' => [
                'status' => MediaStatusType::VideoFailed,
                'failureReason' => self::FAILURE_REASON,
                'originAssetId' => null,
                'originAssetFileId' => null,
            ],
        ];
    }
}
