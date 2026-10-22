<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFactory;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Event\MetadataProcessedEvent;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use Redis;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

// No API surface exposes the lifetime of the duplicate-check lock, so the facade is driven directly.
final class AudioStatusFacadeTest extends CoreDamKernelTestCase
{
    private const string DATA_PATH = __DIR__ . '/../../data/Files/';
    private const string FILE_NAME = 'audioa';

    public function testDuplicateCheckLockLastsAsLongAsTheAckDeadline(): void
    {
        $audioFile = $this->createUploadedAudio();
        $redis = $this->getService('TestRedis');
        self::assertInstanceOf(Redis::class, $redis);
        $remainingTtl = null;
        $this->getService(EventDispatcherInterface::class)->addListener(
            MetadataProcessedEvent::class,
            function () use (&$remainingTtl, $redis, $audioFile): void {
                $lockKey = ResourceLocker::REDIS_LOCK_PREFIX
                    . $audioFile->getAssetType()->value
                    . '_' . $audioFile->getLicence()->getId()
                    . '_' . $audioFile->getAssetAttributes()->getChecksum();
                $remainingTtl = $redis->pttl($lockKey);
            }
        );

        $this->getService(AssetFileStatusFacadeProvider::class)
            ->getStatusFacade($audioFile)
            ->storeAndProcess($audioFile, $this->getFile(), dispatchPropertyRefresh: false);

        self::assertIsInt($remainingTtl);
        self::assertGreaterThan(ResourceLocker::DEFAULT_TTL * 1_000, $remainingTtl);
        self::assertTrue($audioFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
    }

    public function testFailureThatClosedEntityManagerStoresFailedStatus(): void
    {
        $audioFile = $this->createUploadedAudio();
        $audioFileId = $audioFile->getId();
        $this->getService(EventDispatcherInterface::class)->addListener(
            MetadataProcessedEvent::class,
            function (): void {
                $this->entityManager->close();

                throw new RuntimeException('closes the entity manager');
            }
        );

        $result = $this->getService(AssetFileStatusFacadeProvider::class)
            ->getStatusFacade($audioFile)
            ->storeAndProcess($audioFile, $this->getFile(), dispatchPropertyRefresh: false);

        self::assertTrue($this->entityManager->isOpen());
        self::assertNotSame($audioFile, $result);
        $this->entityManager->clear();
        $stored = $this->entityManager->find(AudioFile::class, $audioFileId);
        self::assertInstanceOf(AudioFile::class, $stored);
        self::assertTrue($stored->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Failed));
        self::assertTrue($result->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Failed));
    }

    private function getFile(): AdapterFile
    {
        return new AdapterFile(
            path: self::DATA_PATH . self::FILE_NAME,
            adapterPath: self::FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(self::DATA_PATH),
        );
    }

    private function createUploadedAudio(): AudioFile
    {
        $licence = $this->getService(AssetLicenceRepository::class)->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        self::assertNotNull($licence);
        $audioFile = $this->getService(AudioFactory::class)->createFromFile(
            $this->getFile(),
            $licence,
            Uuid::v4()->toRfc4122(),
        );
        self::assertInstanceOf(AudioFile::class, $audioFile);
        $audioFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $audioFile->getFlags()->setProcessedMetadata(false);

        return $this->getService(AudioManager::class)->create($audioFile);
    }
}
