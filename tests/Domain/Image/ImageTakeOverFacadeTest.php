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
use AnzuSystems\CoreDamBundle\Domain\Image\ImageTakeOverFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTakeOverRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;

final class ImageTakeOverFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';

    private ImageTakeOverFacade $imageTakeOverFacade;
    private AssetLicenceManager $assetLicenceManager;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetFileStatusFacadeProvider $facadeProvider;
    private FileSystemProvider $fileSystemProvider;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageTakeOverFacade = $this->getService(ImageTakeOverFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->facadeProvider = $this->getService(AssetFileStatusFacadeProvider::class);
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    public function testLicenceAllowingDirectUseReturnsTheRequestedFile(): void
    {
        $source = $this->createImage($this->createLicence());

        $result = $this->imageTakeOverFacade->takeOver(
            $this->request($source, $this->createLicence())
        );

        self::assertFalse($result->isTakenOver());
        self::assertSame((string) $source->getId(), $result->getImageFileId());
        self::assertNull($result->getTakenOverFromId());
    }

    public function testLicenceForbiddingDirectUseCopiesTheFileIntoTheTargetLicence(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();

        $result = $this->imageTakeOverFacade->takeOver($this->request($source, $target));

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

        $firstCopy = $this->findImage(
            $this->imageTakeOverFacade->takeOver($this->request($source, $firstTarget))->getImageFileId()
        );
        $secondResult = $this->imageTakeOverFacade->takeOver(
            $this->request($firstCopy, $this->createLicence())
        );

        self::assertTrue($secondResult->isTakenOver());
        self::assertSame((string) $source->getId(), $secondResult->getTakenOverFromId());
    }

    public function testFileAlreadyPresentInTheTargetLicenceIsReused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence();

        $first = $this->imageTakeOverFacade->takeOver($this->request($source, $target));
        $second = $this->imageTakeOverFacade->takeOver($this->request($source, $target));

        self::assertSame($first->getImageFileId(), $second->getImageFileId());
    }

    public function testSingleUseSourceIsRefusedWhenTheTargetLicenceAlreadyHasTheFile(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $this->imageTakeOverFacade->takeOver($this->request($source, $target));

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_TAKE_OVER_CONFLICT,
            fn (): mixed => $this->imageTakeOverFacade->takeOver($this->request($source, $target)),
        );
    }

    public function testRequestWithoutTargetOnlyReportsWhetherDirectUseIsAllowed(): void
    {
        $usable = $this->createImage($this->createLicence());
        self::assertFalse($this->imageTakeOverFacade->takeOver($this->request($usable))->isTakenOver());

        $agencyImage = $this->createImage($this->createLicence(directUseAllowed: false));

        $this->assertForbiddenDetail(
            ForbiddenOperationException::IMAGE_DIRECT_USE_DISABLED,
            fn (): mixed => $this->imageTakeOverFacade->takeOver($this->request($agencyImage)),
        );
    }

    public function testTargetLicenceWithoutManualUploadIsRefused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $target = $this->createLicence(manualUploadAllowed: false);

        $this->assertForbiddenDetail(
            ForbiddenOperationException::LICENCE_MANUAL_UPLOAD_DISABLED,
            fn (): mixed => $this->imageTakeOverFacade->takeOver($this->request($source, $target)),
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
            fn (): mixed => $this->imageTakeOverFacade->takeOver($this->request($source, $this->createLicence())),
        );
    }

    public function testTargetLicenceFromAnotherExtSystemIsRefused(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));

        $this->expectException(ValidationException::class);
        $this->imageTakeOverFacade->takeOver(
            $this->request($source, $this->createLicence(extSystemId: ExtSystemFixtures::ID_BLOG))
        );
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

    private function request(ImageFile $imageFile, ?AssetLicence $targetLicence = null): ImageTakeOverRequestDto
    {
        return (new ImageTakeOverRequestDto())
            ->setImageFile($imageFile)
            ->setTargetAssetLicence($targetLicence);
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

    private function createImage(AssetLicence $licence): ImageFile
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(AbstractAssetFileFixtures::DATA_PATH);
        $file = new AdapterFile(
            path: AbstractAssetFileFixtures::DATA_PATH . self::SOURCE_FILE_NAME,
            adapterPath: self::SOURCE_FILE_NAME,
            filesystem: $fileSystem,
        );

        /** @var ImageFile $image */
        $image = $this->imageFactory->createFromFile($file, $licence);
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        return $this->imageManager->create($image);
    }
}
