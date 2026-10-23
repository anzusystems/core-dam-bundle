<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Messenger\Handler;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\AssetFileStorageOperator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor\FileAttributesProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Messenger\Handler\AssetFileChangeStateHandler;
use AnzuSystems\CoreDamBundle\Messenger\Message\ImageFileChangeStateMessage;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Uid\Uuid;

final class AssetFileChangeStateHandlerLockTest extends CoreDamKernelTestCase
{
    private const string DATA_PATH = __DIR__ . '/../../data/Files/';
    private const string FILE_NAME = 'exifTags.jpg';

    public function testFileLockIsHeldDuringProcessing(): void
    {
        $imageFile = $this->createStoredImage();
        $lockedDuringProcessing = [];
        $this->getService(EventDispatcherInterface::class)->addListener(
            MetadataProcessedEvent::class,
            function () use (&$lockedDuringProcessing, $imageFile): void {
                try {
                    $this->getService(ResourceLocker::class)->lock($imageFile, blocking: false);
                    $lockedDuringProcessing[] = false;
                } catch (RuntimeException $exception) {
                    $lockedDuringProcessing[] = 'locked_by_another_process' === $exception->getMessage();
                }
            }
        );

        $this->getService(AssetFileChangeStateHandler::class)->handleImageFile(new ImageFileChangeStateMessage($imageFile));

        self::assertSame([true], $lockedDuringProcessing);
        self::assertTrue($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testStaleSnapshotOfFileProcessedByAnotherWorkerIsReloadedAfterFreeLock(): void
    {
        $imageFile = $this->createStaleStoredImageProcessedByAnotherWorker();
        $imageId = $imageFile->getId();
        $metadataProcessed = 0;
        $this->getService(EventDispatcherInterface::class)->addListener(
            MetadataProcessedEvent::class,
            function () use (&$metadataProcessed): void {
                ++$metadataProcessed;
            }
        );

        $this->getService(AssetFileChangeStateHandler::class)->handleImageFile(new ImageFileChangeStateMessage($imageFile));

        self::assertSame(App::ZERO, $metadataProcessed);
        $this->entityManager->clear();
        $reloaded = $this->getService(AssetFileRepository::class)->find($imageId);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testExpiredFileLockOfCrashedWorkerDoesNotBlock(): void
    {
        $imageFile = $this->createStoredImage();
        $crashedWorkerLock = (new LockFactory(new RedisStore($this->getService('TestRedis'))))->createLock(
            ResourceLocker::REDIS_LOCK_PREFIX . ImageFile::getResourceName() . '_' . $imageFile->getId(),
            ttl: 1.0,
            autoRelease: false,
        );
        self::assertTrue($crashedWorkerLock->acquire());

        try {
            $this->getService(ResourceLocker::class)->lock($imageFile, blocking: false);
            self::fail('The hand-made lock must be seen by ResourceLocker.');
        } catch (RuntimeException $exception) {
            self::assertSame('locked_by_another_process', $exception->getMessage());
        }

        $this->getService(AssetFileChangeStateHandler::class)->handleImageFile(new ImageFileChangeStateMessage($imageFile));

        self::assertTrue($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    private function createStaleStoredImageProcessedByAnotherWorker(): ImageFile
    {
        $imageFile = $this->createStoredImage();
        $imageFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Processed);
        $this->entityManager->flush();
        $imageFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Stored);
        self::assertTrue($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Stored), 'The handler must start with the stale snapshot.');

        return $imageFile;
    }

    private function createStoredImage(): ImageFile
    {
        $file = new AdapterFile(
            path: self::DATA_PATH . self::FILE_NAME,
            adapterPath: self::FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(self::DATA_PATH),
        );
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::LICENCE_ID);
        self::assertNotNull($licence);
        $imageFile = $this->getService(ImageFactory::class)->createFromFile($file, $licence, Uuid::v4()->toRfc4122());
        self::assertInstanceOf(ImageFile::class, $imageFile);
        $attributesProcessor = $this->getService(FileAttributesProcessor::class);
        $attributesProcessor->processAttributes($imageFile, $file);
        $attributesProcessor->processChecksum($imageFile, $file);
        $imageFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Stored);
        $imageFile->getFlags()->setProcessedMetadata(false);
        $imageFile = $this->getService(ImageManager::class)->create($imageFile);
        $this->getService(AssetFileStorageOperator::class)->save($imageFile, $file);
        $this->entityManager->flush();

        return $imageFile;
    }
}
