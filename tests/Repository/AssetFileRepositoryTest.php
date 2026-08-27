<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Repository;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use DateTimeImmutable;

final class AssetFileRepositoryTest extends CoreDamKernelTestCase
{
    private const string STORAGE_NAME = 'inbox.storage';
    private const string IMPORTED_PATH = 'tasr/photo.jpg';
    private const string OTHER_PATH = 'tasr/other-photo.jpg';

    private AssetFileRepository $repository;
    private ImageFileRepository $imageFileRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getService(AssetFileRepository::class);
        $this->imageFileRepository = $this->getService(ImageFileRepository::class);
    }

    /**
     * The auto import decides whether a source path is permanently broken from these reasons, so the
     * query must see only this path's own failures, and the newest ones within the limit.
     */
    public function testFindFailedReasonsByOriginStorageReturnsNewestFailuresOfThatPathOnly(): void
    {
        $this->storeAttempt(ImageFixtures::IMAGE_ID_1_1, self::IMPORTED_PATH, AssetFileProcessStatus::Failed, AssetFileFailedType::Unknown, '-3 hours');
        $this->storeAttempt(ImageFixtures::IMAGE_ID_1_2, self::IMPORTED_PATH, AssetFileProcessStatus::Failed, AssetFileFailedType::DownloadFailed, '-2 hours');
        $this->storeAttempt(ImageFixtures::IMAGE_ID_2, self::IMPORTED_PATH, AssetFileProcessStatus::Failed, AssetFileFailedType::InvalidMimeType, '-1 hour');
        $this->storeAttempt(ImageFixtures::IMAGE_ID_3, self::OTHER_PATH, AssetFileProcessStatus::Failed, AssetFileFailedType::InvalidSize, '-1 minute');
        $this->storeAttempt(ImageFixtures::IMAGE_UPLOADING_ID_4, self::IMPORTED_PATH, AssetFileProcessStatus::Uploading, AssetFileFailedType::InvalidChecksum, '-1 minute');
        $this->entityManager->flush();

        $originStorage = new OriginStorage(self::STORAGE_NAME, self::IMPORTED_PATH);

        self::assertSame(
            [AssetFileFailedType::InvalidMimeType, AssetFileFailedType::DownloadFailed, AssetFileFailedType::Unknown],
            $this->repository->findFailedReasonsByOriginStorage($originStorage),
        );
        self::assertSame(
            [AssetFileFailedType::InvalidMimeType],
            $this->repository->findFailedReasonsByOriginStorage($originStorage, limit: 1),
        );
    }

    public function testFindFailedReasonsByOriginStorageReturnsNothingForAnUntouchedPath(): void
    {
        self::assertSame(
            [],
            $this->repository->findFailedReasonsByOriginStorage(new OriginStorage(self::STORAGE_NAME, self::OTHER_PATH)),
        );
    }

    private function storeAttempt(
        string $imageId,
        string $path,
        AssetFileProcessStatus $status,
        AssetFileFailedType $failReason,
        string $createdAtModifier,
    ): void {
        $image = $this->imageFileRepository->find($imageId);
        self::assertInstanceOf(ImageFile::class, $image);

        $image->getAssetAttributes()
            ->setOriginStorage(new OriginStorage(self::STORAGE_NAME, $path))
            ->setStatus($status)
            ->setFailReason($failReason)
        ;
        $image->setCreatedAt(new DateTimeImmutable($createdAtModifier));
    }
}
