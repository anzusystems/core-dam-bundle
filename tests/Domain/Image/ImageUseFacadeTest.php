<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Event\AssetChangedEvent;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCopyStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ImageUseFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';

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
        self::assertSame((string) $source->getId(), $copy->getAssetAttributes()->getOriginAssetId());
        self::assertSame((string) $source->getId(), $copy->getAssetAttributes()->getTakenOverFromId());
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

    public function testReuseConflictsWithAFileTakenOverFromAnotherGroup(): void
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

    public function testSingleUseSourceAdoptsASharedFileUploadedBeforeItAndMakesItSingleUse(): void
    {
        $target = $this->createLicence();
        $existing = $this->createImage($target);
        self::assertFalse($existing->getFlags()->isSingleUse());
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));

        $result = $this->useOne($source, $target);

        self::assertSame((string) $existing->getId(), $result->getImageFileId());
        self::assertSame((string) $source->getId(), $result->getTakenOverFromId());
        self::assertTrue($result->isSingleUse());
        $this->entityManager->clear();
        self::assertTrue($this->findImage((string) $existing->getId())->getFlags()->isSingleUse());
    }

    public function testRetryOfAPickRepairsAStaleSharedFlagOfAFileTakenOverFromASingleUseSource(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $copy = $this->findImage($this->useOne($source, $target)->getImageFileId());
        $copy->getFlags()->setSingleUse(false);
        $this->entityManager->flush();

        $result = $this->useOne($source, $target);

        self::assertSame((string) $copy->getId(), $result->getImageFileId());
        self::assertTrue($result->isSingleUse());
    }

    public function testFileWithTheSameBytesUploadedBeforeTheSourceIsAdoptedUnderTheSourceRootAtThePick(): void
    {
        $target = $this->createLicence();
        $existing = $this->createImage($target);
        $source = $this->createImage($this->createLicence(directUseAllowed: false));

        $result = $this->useOne($source, $target);

        self::assertSame((string) $existing->getId(), $result->getImageFileId());
        self::assertSame((string) $source->getId(), $result->getTakenOverFromId());
    }

    public function testUploadOfAPhotoAnAgencyLicenceHoldsJoinsItsGroupAsSingleUse(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));

        $upload = $this->createImage($this->createLicence());

        self::assertSame((string) $source->getId(), $upload->getAssetAttributes()->getTakenOverFromId());
        self::assertTrue($upload->getFlags()->isSingleUse());
    }

    public function testUploadOfAPhotoAnAgencyLicenceOfAnotherExtSystemHoldsStaysIndependent(): void
    {
        $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));

        $upload = $this->createImage($this->createLicence(extSystemId: ExtSystemFixtures::ID_BLOG));

        self::assertFalse($upload->getAssetAttributes()->isTakenOver());
        self::assertFalse($upload->getFlags()->isSingleUse());
    }

    public function testEveryPickOfATakenOverFileDispatchesAssetChangedSoARetryRepairsALostNotification(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence();
        $existing = $this->createImage($target);
        $dispatched = [];
        $this->getService(EventDispatcherInterface::class)->addListener(
            AssetChangedEvent::class,
            static function (AssetChangedEvent $event) use (&$dispatched): void {
                $dispatched[] = $event->getAffectedAssets()->map(
                    static fn (Asset $asset): string => (string) $asset->getId(),
                )->getValues();
            },
        );

        $this->useOne($source, $target);
        $this->useOne($source, $target);

        self::assertSame(
            [[(string) $existing->getAsset()->getId()], [(string) $existing->getAsset()->getId()]],
            $dispatched,
        );
    }

    public function testCopyPickedBackIntoTheLicenceOfItsRootReturnsTheRoot(): void
    {
        $root = $this->createImage($this->createLicence(directUseAllowed: false));
        $copy = $this->findImage($this->useOne($root, $this->createLicence(directUseAllowed: false))->getImageFileId());

        $result = $this->useOne($copy, $root->getLicence());

        self::assertSame((string) $root->getId(), $result->getImageFileId());
        self::assertNull($result->getTakenOverFromId());
    }

    public function testCopyToLicenceAdoptsAFileUploadedBeforeTheSourceAndMakesItSingleUse(): void
    {
        $target = $this->createLicence();
        $existing = $this->createImage($target);
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));

        $result = $this->copyOne($source, $target);

        self::assertSame(AssetFileCopyStatus::Exists, $result->getResult());
        self::assertSame((string) $existing->getId(), (string) $result->getTargetMainFile()?->getId());
        $this->entityManager->clear();
        $adopted = $this->findImage((string) $existing->getId());
        self::assertSame((string) $source->getId(), $adopted->getAssetAttributes()->getTakenOverFromId());
        self::assertTrue($adopted->getFlags()->isSingleUse());
    }

    public function testCopyToLicenceDoesNotHandBackAFileOfAnotherGroup(): void
    {
        $firstSource = $this->createImage($this->createLicence(directUseAllowed: false));
        $secondSource = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence();
        $taken = $this->findImage($this->useOne($firstSource, $target)->getImageFileId());

        $result = $this->copyOne($secondSource, $target);

        self::assertSame(AssetFileCopyStatus::NotAllowed, $result->getResult());
        self::assertNull($result->getTargetMainFile());
        self::assertSame((string) $firstSource->getId(), $taken->getAssetAttributes()->getTakenOverFromId());
    }

    public function testForceTakesOverEvenWhenTheLicenceAllowsDirectUse(): void
    {
        $source = $this->createImage($this->createLicence());
        $target = $this->createLicence();

        $result = $this->useOne($source, $target, forceTakeOver: true);

        self::assertNotSame((string) $source->getId(), $result->getImageFileId());
        self::assertSame((int) $target->getId(), $result->getLicenceId());
        self::assertSame((string) $source->getId(), $result->getTakenOverFromId());
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

    private function useOne(ImageFile $imageFile, ?AssetLicence $targetLicence = null, bool $forceTakeOver = false): ImageUseResultDto
    {
        $results = $this->imageUseFacade->useImages(
            (new ImageUseRequestDto())->setItems(new ArrayCollection([$this->item($imageFile, $targetLicence, $forceTakeOver)]))
        )->getResults();

        /** @var ImageUseResultDto $result */
        $result = $results->first();

        return $result;
    }

    private function copyOne(ImageFile $imageFile, AssetLicence $targetLicence): AssetFileCopyResultDto
    {
        $results = $this->getService(ImageCopyFacade::class)->prepareCopyList(new ArrayCollection([
            (new ImageCopyDto())->setAsset($imageFile->getAsset())->setTargetAssetLicence($targetLicence),
        ]));

        /** @var AssetFileCopyResultDto $result */
        $result = $results->first();

        return $result;
    }

    private function item(ImageFile $imageFile, ?AssetLicence $targetLicence = null, bool $forceTakeOver = false): ImageUseItemDto
    {
        return (new ImageUseItemDto())
            ->setImageFile($imageFile)
            ->setTargetAssetLicence($targetLicence)
            ->setForceTakeOver($forceTakeOver);
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
