<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use Closure;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

// No API surface exposes lock scope and transaction boundaries of the processing flow, so the facade is driven directly.
final class ImageStatusFacadeTest extends CoreDamKernelTestCase
{
    private const string DATA_PATH = __DIR__ . '/../../data/Files/';
    private const string FILE_NAME = 'exifTags.jpg';
    private const string LOCKED = 'locked_by_another_process';
    private const string SUGGESTER_LOCK_PREFIX = 'lock_suggester_';

    public function testLocksByLicenceAndChecksum(): void
    {
        $imageFile = $this->createUploadedImage();
        $prefix = $imageFile->getAssetType()->value . '_' . $imageFile->getLicence()->getId();
        $perChecksumLockError = null;
        $perLicenceLockError = null;
        $this->onMetadataProcessed(function () use (&$perChecksumLockError, &$perLicenceLockError, $imageFile, $prefix): void {
            $checksum = $imageFile->getAssetAttributes()->getChecksum();
            $perChecksumLockError = $this->tryLock($prefix . '_' . $checksum);
            $perLicenceLockError = $this->tryLock($prefix);
        });

        $this->process($imageFile);

        self::assertSame(self::LOCKED, $perChecksumLockError);
        self::assertNull($perLicenceLockError);
        self::assertTrue($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testMetadataRunsOutsideFacadeTransaction(): void
    {
        $imageFile = $this->createUploadedImage();
        $connection = $this->entityManager->getConnection();
        $levelBefore = $connection->getTransactionNestingLevel();
        $levelDuringMetadata = null;
        $this->onMetadataProcessed(static function () use (&$levelDuringMetadata, $connection): void {
            $levelDuringMetadata = $connection->getTransactionNestingLevel();
        });

        $this->process($imageFile);

        self::assertSame($levelBefore, $levelDuringMetadata);
        self::assertTrue($imageFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testMetadataFlushRunsUnderSuggesterLock(): void
    {
        $imageFile = $this->createUploadedImage();
        $suggesterLock = self::SUGGESTER_LOCK_PREFIX . $imageFile->getAsset()->getId();
        $lockHeldOnFlush = [];
        $metadataProcessed = false;
        $this->entityManager->getEventManager()->addEventListener(
            Events::postFlush,
            new class(function () use (&$lockHeldOnFlush, &$metadataProcessed, $suggesterLock): void {
                if (false === $metadataProcessed) {
                    $lockHeldOnFlush[] = self::LOCKED === $this->tryLock($suggesterLock);
                }
            }) {
                public function __construct(
                    private readonly Closure $callback
                ) {
                }

                public function postFlush(PostFlushEventArgs $args): void
                {
                    ($this->callback)();
                }
            }
        );
        $this->onMetadataProcessed(static function () use (&$metadataProcessed): void {
            $metadataProcessed = true;
        });

        $this->process($imageFile);

        self::assertNotEmpty($lockHeldOnFlush);
        self::assertTrue(
            $lockHeldOnFlush[array_key_last($lockHeldOnFlush)],
            'The last flush before the metadata processed event must run under the suggester lock.'
        );
    }

    public function testSecondIdenticalFileIsDuplicate(): void
    {
        $first = $this->createUploadedImage();
        $second = $this->createUploadedImage();

        $this->process($first);
        $this->process($second);

        self::assertTrue($first->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
        self::assertTrue($second->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Duplicate));
        self::assertSame((string) $first->getId(), $second->getAssetAttributes()->getOriginAssetId());
    }

    private function onMetadataProcessed(callable $listener): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener(MetadataProcessedEvent::class, $listener);
    }

    private function tryLock(string $name): ?string
    {
        $locker = $this->getService(ResourceLocker::class);

        try {
            $locker->lock($name, blocking: false);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }
        $locker->unLock($name);

        return null;
    }

    private function getFile(): AdapterFile
    {
        return new AdapterFile(
            path: self::DATA_PATH . self::FILE_NAME,
            adapterPath: self::FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(self::DATA_PATH),
        );
    }

    private function createUploadedImage(): ImageFile
    {
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::LICENCE_ID);
        self::assertNotNull($licence);
        $imageFile = $this->getService(ImageFactory::class)->createFromFile(
            $this->getFile(),
            $licence,
            Uuid::v4()->toRfc4122(),
        );
        self::assertInstanceOf(ImageFile::class, $imageFile);
        $imageFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $imageFile->getFlags()->setProcessedMetadata(false);

        return $this->getService(ImageManager::class)->create($imageFile);
    }

    private function process(ImageFile $imageFile): void
    {
        $this->getService(AssetFileStatusFacadeProvider::class)
            ->getStatusFacade($imageFile)
            ->storeAndProcess($imageFile, $this->getFile(), dispatchPropertyRefresh: false);
    }
}
