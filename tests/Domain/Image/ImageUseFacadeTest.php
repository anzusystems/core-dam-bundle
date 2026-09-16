<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class ImageUseFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';
    // Two sources of one batch must not share bytes: identical checksums make the copy of the second
    // dedup onto the copy of the first, which is a genuine group clash rather than what is tested here.
    private const string OTHER_SOURCE_FILE_NAME = 'text_image_200x200.jpg';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000002';

    private ImageUseFacade $imageUseFacade;
    private AssetLicenceManager $assetLicenceManager;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetFileStatusFacadeProvider $facadeProvider;
    private FileSystemProvider $fileSystemProvider;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageUseFacade = $this->getService(ImageUseFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->facadeProvider = $this->getService(AssetFileStatusFacadeProvider::class);
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    public function testLicenceAllowingDirectUseReturnsTheRequestedFile(): void
    {
        $source = $this->createImage($this->createLicence());

        $result = $this->useOne($source, $this->createLicence());

        self::assertFalse($result->isTakenOver());
        self::assertSame((string) $source->getId(), $result->getImageFileId());
        self::assertNull($result->getTakenOverFromId());
    }

    public function testLicenceForbiddingDirectUseCopiesTheFileIntoTheTargetLicence(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        $result = $this->useOne($source, $target);

        self::assertTrue($result->isTakenOver());
        self::assertNotSame((string) $source->getId(), $result->getImageFileId());
        self::assertSame((int) $target->getId(), $result->getLicenceId());
        self::assertSame((string) $source->getId(), $result->getTakenOverFromId());
        // The agency original stays single use, so its copy must be too.
        self::assertTrue($result->isSingleUse());

        $copy = $this->findImage($result->getImageFileId());
        self::assertSame(AssetFileProcessStatus::Processed, $copy->getAssetAttributes()->getStatus());
        self::assertSame(AssetStatus::WithFile, $copy->getAsset()->getAttributes()->getStatus());
        self::assertSame(App::EMPTY_STRING, $copy->getAssetAttributes()->getOriginAssetId());
        // The copy owns its bytes: a new path, physically present in the storage of the target licence.
        self::assertNotSame($source->getFilePath(), $copy->getFilePath());
        self::assertTrue(
            $this->fileSystemProvider->getFilesystemByStorable($copy)->fileExists($copy->getFilePath())
        );
    }

    public function testTakeOverOfATakeOverKeepsPointingAtTheOriginal(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $firstTarget = $this->createLicence(directUseAllowed: false);

        $firstCopy = $this->findImage($this->useOne($source, $firstTarget)->getImageFileId());
        $secondResult = $this->useOne($firstCopy, $this->createLicence());

        self::assertTrue($secondResult->isTakenOver());
        self::assertSame((string) $source->getId(), $secondResult->getTakenOverFromId());
    }

    public function testFileAlreadyPresentInTheTargetLicenceIsReused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence();

        $first = $this->useOne($source, $target);
        $second = $this->useOne($source, $target);

        self::assertSame($first->getImageFileId(), $second->getImageFileId());
    }

    public function testSingleUseSourceReuseIsIdempotentWhenTheTargetLicenceAlreadyHasTheFile(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $first = $this->useOne($source, $target);

        // A retry after a timeout, or the same photo picked twice: the take-over already happened, so this
        // must succeed again instead of being refused.
        $second = $this->useOne($source, $target);

        self::assertTrue($second->isTakenOver());
        self::assertSame($first->getImageFileId(), $second->getImageFileId());
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testSingleUseSourceReuseConflictsWithAFileTakenOverFromAnotherGroup(): void
    {
        $firstSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $secondSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        // Both sources share the same bytes (the fixture file), so the second take-over hits the checksum
        // match created by the first — but that copy's root belongs to firstSource, not secondSource.
        $this->useOne($firstSource, $target);

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT,
            fn (): mixed => $this->useOne($secondSource, $target),
        );
    }

    public function testRequestWithoutTargetOnlyReportsWhetherDirectUseIsAllowed(): void
    {
        $usable = $this->createImage($this->createLicence());
        self::assertFalse($this->useOne($usable)->isTakenOver());

        $agencyImage = $this->createImage($this->createLicence(directUseAllowed: false));

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED,
            fn (): mixed => $this->useOne($agencyImage),
        );
    }

    public function testTargetLicenceWithoutManualUploadIsRefused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence(manualUploadAllowed: false);

        $this->assertForbiddenDetail(
            ForbiddenOperationException::LICENCE_MANUAL_UPLOAD_DISABLED,
            fn (): mixed => $this->useOne($source, $target),
        );
    }

    public function testUnprocessedSourceIsRefused(): void
    {
        $licence = $this->createLicence(directUseAllowed: false);
        $source = $this->imageManager->create(
            $this->imageFactory->createFromUrl($licence, 'https://example.test/take-over.jpg')
        );

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_TAKE_OVER_SOURCE_INVALID,
            fn (): mixed => $this->useOne($source, $this->createLicence()),
        );
    }

    public function testTargetLicenceFromAnotherExtSystemIsRefused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));

        $this->expectException(ValidationException::class);
        $this->useOne($source, $this->createLicence(extSystemId: ExtSystemFixtures::ID_BLOG));
    }

    public function testSingleUseFileIsNotClaimedForAnybody(): void
    {
        $source = $this->createImage($this->createLicence());

        $this->useOne($source, $this->createLicence());

        $this->assertFree($source);
    }

    public function testSingleUseFileIsClaimedOnTheWholeGroup(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        $result = $this->useOne($source, $target);
        $copy = $this->findImage($result->getImageFileId());

        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertHolder($copy, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testSameHolderClaimingAgainIsANoOp(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->useOne($source, $target);

        $result = $this->useOne($source, $target);

        self::assertTrue($result->isTakenOver());
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testForeignHolderIsRefusedWithTheConflict(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->useOne($source, $target);

        try {
            $this->useOne($source, $target, holderId: self::OTHER_HOLDER_ID);
            self::fail('Expected a usage conflict.');
        } catch (ImageUsageConflictException $exception) {
            $conflicts = $exception->getConflicts();
            self::assertCount(1, $conflicts);
            self::assertSame(self::HOLDER_NAME, $conflicts[0]->getHolderName());
            self::assertSame(self::HOLDER_ID, $conflicts[0]->getHolderId());
        }
        // A conflict leaves the existing holder untouched.
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testBatchClaimsEveryItemForTheSameHolder(): void
    {
        $firstSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $secondSource = $this->createImage(
            $this->createLicence(directUseAllowed: false, singleUseEnforced: true),
            self::OTHER_SOURCE_FILE_NAME,
        );
        $target = $this->createLicence();

        $results = $this->imageUseFacade->useImages(
            $this->batchRequest([
                $this->item($firstSource, $target),
                $this->item($secondSource, $target),
            ])
        )->getResults();

        self::assertCount(2, $results);
        $this->assertHolder($firstSource, self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertHolder($secondSource, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testBatchIsAllOrNothingWhenOneItemConflicts(): void
    {
        $freeSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $heldSource = $this->createImage(
            $this->createLicence(directUseAllowed: false, singleUseEnforced: true),
            self::OTHER_SOURCE_FILE_NAME,
        );
        $target = $this->createLicence();
        $this->useOne($heldSource, $target);

        try {
            $this->imageUseFacade->useImages(
                $this->batchRequest([
                    $this->item($freeSource, $target),
                    $this->item($heldSource, $target),
                ], holderId: self::OTHER_HOLDER_ID)
            );
            self::fail('Expected a usage conflict.');
        } catch (ImageUsageConflictException $exception) {
            self::assertCount(1, $exception->getConflicts());
        }

        // Nothing of the batch was written — the free photo was not claimed either.
        $this->assertFree($freeSource);
    }

    /**
     * The detail is what the caller reacts to; every forbidden operation shares the same message.
     */
    private function assertForbiddenDetail(string $detail, callable $operation): void
    {
        try {
            $operation();
        } catch (ForbiddenOperationException $exception) {
            self::assertSame($detail, $exception->getDetail());

            return;
        }

        self::fail(sprintf('Expected a forbidden operation (%s).', $detail));
    }

    private function assertHolder(ImageFile $image, string $holderName, string $holderId): void
    {
        $this->entityManager->clear();
        $attributes = $this->findImage((string) $image->getId())->getAssetAttributes();

        self::assertSame($holderName, $attributes->getUsedByHolderName());
        self::assertSame($holderId, $attributes->getUsedByHolderId());
    }

    private function assertFree(ImageFile $image): void
    {
        $this->assertHolder($image, App::EMPTY_STRING, App::EMPTY_STRING);
    }

    private function useOne(
        ImageFile $imageFile,
        ?AssetLicence $targetLicence = null,
        string $holderId = self::HOLDER_ID,
    ): ImageUseResultDto {
        $results = $this->imageUseFacade->useImages(
            $this->batchRequest([$this->item($imageFile, $targetLicence)], holderId: $holderId)
        )->getResults();

        /** @var ImageUseResultDto $result */
        $result = $results->first();

        return $result;
    }

    private function item(ImageFile $imageFile, ?AssetLicence $targetLicence = null): ImageUseItemDto
    {
        return (new ImageUseItemDto())
            ->setImageFile($imageFile)
            ->setTargetAssetLicence($targetLicence);
    }

    /**
     * @param list<ImageUseItemDto> $items
     */
    private function batchRequest(array $items, string $holderId = self::HOLDER_ID): ImageUseRequestDto
    {
        return (new ImageUseRequestDto())
            ->setHolder((new ImageHolderDto())->setName(self::HOLDER_NAME)->setId($holderId))
            ->setItems(new ArrayCollection($items));
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }

    private function createLicence(
        bool $directUseAllowed = true,
        bool $manualUploadAllowed = true,
        bool $singleUseEnforced = false,
        int $extSystemId = ExtSystemFixtures::ID_CMS,
    ): AssetLicence {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, $extSystemId);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('take-over-test-' . ++$this->extIdSequence);
        $licence->getFlags()
            ->setDirectUseAllowed($directUseAllowed)
            ->setManualUploadAllowed($manualUploadAllowed)
            ->setSingleUseEnforced($singleUseEnforced)
        ;

        return $this->assetLicenceManager->create($licence);
    }

    private function createImage(AssetLicence $licence, string $fileName = self::SOURCE_FILE_NAME): ImageFile
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(AbstractAssetFileFixtures::DATA_PATH);
        $file = new AdapterFile(
            path: AbstractAssetFileFixtures::DATA_PATH . $fileName,
            adapterPath: $fileName,
            filesystem: $fileSystem,
        );

        /** @var ImageFile $image */
        $image = $this->imageFactory->createFromFile($file, $licence);
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        return $this->imageManager->create($image);
    }
}
