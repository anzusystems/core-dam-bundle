<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetPropertiesRefresher;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFirstUseFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\Handler\ImageUsageConflictExceptionHandler;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\SerializerBundle\Serializer;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Component\HttpFoundation\Response;

final class ImageUseFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';
    private const string SOURCE_FILE_NAME_WITH_OTHER_BYTES = 'text_image_200x200.jpg';
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

        self::assertSame((string) $source->getId(), $result->getImageFileId());
        self::assertNull($result->getTakenOverFromId());
    }

    public function testLicenceForbiddingDirectUseCopiesTheFileIntoTheTargetLicence(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        $result = $this->useOne($source, $target);

        self::assertNotSame((string) $source->getId(), $result->getImageFileId());
        self::assertSame((int) $target->getId(), $result->getLicenceId());
        self::assertSame((string) $source->getId(), $result->getTakenOverFromId());
        self::assertTrue($result->isSingleUse());

        $copy = $this->findImage($result->getImageFileId());
        self::assertSame(AssetFileProcessStatus::Processed, $copy->getAssetAttributes()->getStatus());
        self::assertSame(AssetStatus::WithFile, $copy->getAsset()->getAttributes()->getStatus());
        self::assertSame(App::EMPTY_STRING, $copy->getAssetAttributes()->getOriginAssetId());
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

        self::assertNotSame((string) $firstCopy->getId(), $secondResult->getImageFileId());
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

        $second = $this->useOne($source, $target);

        self::assertNotSame((string) $source->getId(), $second->getImageFileId());
        self::assertSame($first->getImageFileId(), $second->getImageFileId());
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testSingleUseSourceReuseConflictsWithAFileTakenOverFromAnotherGroup(): void
    {
        $firstSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $secondSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        $this->useOne($firstSource, $target);

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT,
            fn (): mixed => $this->useOne($secondSource, $target),
        );
    }

    public function testSingleUseSourceCannotAdoptAnIndependentlyUploadedSharedTarget(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->createImage($target);

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT,
            fn (): mixed => $this->useOne($source, $target),
        );
    }

    public function testRequestWithoutTargetOnlyReportsWhetherDirectUseIsAllowed(): void
    {
        $usable = $this->createImage($this->createLicence());
        self::assertSame((string) $usable->getId(), $this->useOne($usable)->getImageFileId());

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

    public function testPlainPhotoIsNotClaimedForAnybody(): void
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

    public function testSameHolderClaimingAgainKeepsTheHolderAndPushesTheCheckForward(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->useOne($source, $target);
        $this->makeCheckDue($source);

        $result = $this->useOne($source, $target);

        self::assertNotSame((string) $source->getId(), $result->getImageFileId());
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
        self::assertGreaterThan(
            new DateTimeImmutable(),
            $this->findImage((string) $source->getId())->getAssetAttributes()->getUsedByCheckAfter(),
        );
    }

    public function testForeignHolderIsRefusedWithTheConflict(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->useOne($source, $target);

        try {
            $this->buildImageUseFacade(singleUseEnforced: true)->useImages(
                $this->batchRequest([$this->item($source, $target)], holderId: self::OTHER_HOLDER_ID)
            );
            self::fail('Expected a usage conflict.');
        } catch (ImageUsageConflictException $exception) {
            $response = new ImageUsageConflictExceptionHandler($this->getService(Serializer::class))->getErrorResponse($exception);
            self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
            $body = json_decode((string) $response->getContent(), true);
            self::assertSame(ImageUsageConflictException::ERROR_MESSAGE, $body['error']);
            self::assertSame([[
                'imageFileId' => (string) $source->getId(),
                'holderName' => self::HOLDER_NAME,
                'holderId' => self::HOLDER_ID,
            ]], $body['conflicts']);
        }
        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testForeignHolderIsOverwrittenInSoftMode(): void
    {
        $heldSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $freeSource = $this->createImage(
            $this->createLicence(directUseAllowed: false, singleUseEnforced: true),
            self::SOURCE_FILE_NAME_WITH_OTHER_BYTES,
        );
        $target = $this->createLicence();
        $this->useOne($heldSource, $target);

        $this->imageUseFacade->useImages(
            $this->batchRequest([
                $this->item($heldSource, $target),
                $this->item($freeSource, $target),
            ], holderId: self::OTHER_HOLDER_ID)
        );

        $this->assertHolder($heldSource, self::HOLDER_NAME, self::OTHER_HOLDER_ID);
        $this->assertHolder($freeSource, self::HOLDER_NAME, self::OTHER_HOLDER_ID);
    }

    public function testBatchClaimsEveryItemForTheSameHolder(): void
    {
        $firstSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $secondSource = $this->createImage(
            $this->createLicence(directUseAllowed: false, singleUseEnforced: true),
            self::SOURCE_FILE_NAME_WITH_OTHER_BYTES,
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
            self::SOURCE_FILE_NAME_WITH_OTHER_BYTES,
        );
        $target = $this->createLicence();
        $this->useOne($heldSource, $target);

        try {
            $this->buildImageUseFacade(singleUseEnforced: true)->useImages(
                $this->batchRequest([
                    $this->item($freeSource, $target),
                    $this->item($heldSource, $target),
                ], holderId: self::OTHER_HOLDER_ID)
            );
            self::fail('Expected a usage conflict.');
        } catch (ImageUsageConflictException $exception) {
            self::assertCount(1, $exception->getConflicts());
        }

        $this->assertFree($freeSource);
    }

    public function testSingleUseWithoutAHolderIsLoggedButLetThroughInSoftMode(): void
    {
        $source = $this->createImageWithoutUsageCheck($this->createLicence(singleUseEnforced: true));
        $journal = new TestHandler();

        $this->buildImageUseFacade(singleUseEnforced: false, journal: $journal)->useImages(
            (new ImageUseRequestDto())->setItems(new ArrayCollection([$this->item($source)]))
        );

        self::assertTrue($journal->hasRecordThatContains(
            sprintf('Single use file %s used without a holder', (string) $source->getId()),
            Level::Warning,
        ));
        $this->assertFree($source);
        $this->entityManager->clear();
        self::assertNotNull(
            $this->findImage((string) $source->getId())->getAssetAttributes()->getUsedByCheckAfter(),
            'Used by somebody the register cannot name — the reconcile has to ask who that is.',
        );
    }

    public function testSingleUseWithoutAHolderIsRefusedWhenEnforced(): void
    {
        $source = $this->createImage($this->createLicence(singleUseEnforced: true));

        try {
            $this->buildImageUseFacade(singleUseEnforced: true)->useImages(
                (new ImageUseRequestDto())->setItems(new ArrayCollection([$this->item($source)]))
            );
            self::fail('Expected the holderless claim to be refused.');
        } catch (ForbiddenOperationException $exception) {
            self::assertSame(ForbiddenOperationException::IMAGE_SINGLE_USE_HOLDER_REQUIRED, $exception->getDetail());
        }

        $this->assertFree($source);
    }

    public function testFreeUseWithoutAHolderIsNeitherRefusedNorClaimedWhenEnforced(): void
    {
        $created = $this->createImage($this->createLicence(singleUseEnforced: true));
        $this->entityManager->clear();
        $source = $this->findImage((string) $created->getId());
        $checkAfter = $source->getAssetAttributes()->getUsedByCheckAfter();

        $this->buildImageUseFacade(singleUseEnforced: true)->useImages(
            (new ImageUseRequestDto())->setFreeUse(true)->setItems(new ArrayCollection([$this->item($source)]))
        );

        $this->assertFree($source);
        $this->entityManager->clear();
        $image = $this->findImage((string) $source->getId());
        self::assertEquals($checkAfter, $image->getAssetAttributes()->getUsedByCheckAfter());
        self::assertNotNull($image->getFirstUsedAt());
    }

    public function testPlainImageWithoutAHolderIsRecordedAsUsed(): void
    {
        $source = $this->createImage($this->createLicence());

        $this->imageUseFacade->useImages(
            (new ImageUseRequestDto())->setItems(new ArrayCollection([$this->item($source)]))
        );
        $this->entityManager->clear();

        self::assertNotNull($this->findImage((string) $source->getId())->getFirstUsedAt());
    }

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

    private function buildImageUseFacade(bool $singleUseEnforced, ?TestHandler $journal = null): ImageUseFacade
    {
        $damLogger = $this->getService(DamLogger::class);
        if ($journal instanceof TestHandler) {
            $damLogger = $this->recordingDamLogger($journal);
        }

        $facade = new ImageUseFacade(
            $this->getService(ImageCopyFacade::class),
            $this->getService(AssetFileFirstUseFacade::class),
            $this->getService(AssetManager::class),
            $this->getService(AssetPropertiesRefresher::class),
            $this->getService(AssetFileManager::class),
            $this->getService(AssetFileRepository::class),
            $damLogger,
            singleUseEnforced: $singleUseEnforced,
        );
        $facade->setIndexManager($this->getService(IndexManager::class));

        return $facade;
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

    private function makeCheckDue(ImageFile $image): void
    {
        $this->findImage((string) $image->getId())->getAssetAttributes()->setUsedByCheckAfter(new DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();
    }

    private function createImageWithoutUsageCheck(AssetLicence $licence): ImageFile
    {
        $image = $this->createImage($licence);
        $image->getAssetAttributes()->setUsedByCheckAfter(null);
        $this->entityManager->flush();

        return $image;
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
