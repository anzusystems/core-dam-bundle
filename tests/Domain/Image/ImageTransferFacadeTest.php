<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageTransferFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class ImageTransferFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';
    private const string HOLDER_NAME = 'gallery';
    private const string HOLDER_ID = '42';
    private const string RECEIVER_NAME = 'articleKindStandard';
    private const string RECEIVER_ID = '2f8b0f1e-0000-4000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '43';

    private ImageTransferFacade $imageTransferFacade;
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
        $this->imageTransferFacade = $this->getService(ImageTransferFacade::class);
        $this->imageUseFacade = $this->getService(ImageUseFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->facadeProvider = $this->getService(AssetFileStatusFacadeProvider::class);
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    public function testTransferMovesTheWholeGroupToTheReceiver(): void
    {
        $source = $this->createImage($this->singleUseLicence());
        $copy = $this->findImage($this->useFor($source, self::HOLDER_ID)->getImageFileId());

        $result = $this->transfer([$copy]);

        $this->assertHolder($source, self::RECEIVER_NAME, self::RECEIVER_ID);
        $this->assertHolder($copy, self::RECEIVER_NAME, self::RECEIVER_ID);
        self::assertSame([(string) $copy->getId()], $result->getTransferred());
        self::assertCount(0, $result->getNotHeldByFrom());
    }

    public function testTransferLeavesAGroupSomebodyElseHoldsAndReportsIt(): void
    {
        $source = $this->createImage($this->singleUseLicence());
        $this->useFor($source, self::OTHER_HOLDER_ID);

        $result = $this->transfer([$source]);

        $this->assertHolder($source, self::HOLDER_NAME, self::OTHER_HOLDER_ID);
        self::assertSame([], $result->getTransferred());
        $notHeld = $result->getNotHeldByFrom()->first();
        self::assertNotFalse($notHeld);
        self::assertSame((string) $source->getId(), $notHeld->getDamId());
        self::assertSame(self::OTHER_HOLDER_ID, $notHeld->getHolderId());
    }

    public function testBatchTransfersOnlyTheGroupsTheSenderHolds(): void
    {
        $own = $this->createImage($this->singleUseLicence());
        $ownCopy = $this->findImage($this->useFor($own, self::HOLDER_ID)->getImageFileId());
        $foreign = $this->createImage($this->singleUseLicence());
        $this->useFor($foreign, self::OTHER_HOLDER_ID);

        $this->transfer([$ownCopy, $foreign]);

        $this->assertHolder($own, self::RECEIVER_NAME, self::RECEIVER_ID);
        $this->assertHolder($foreign, self::HOLDER_NAME, self::OTHER_HOLDER_ID);
    }

    public function testTransferGivesAFreeSingleUsePhotoToTheReceiver(): void
    {
        $source = $this->createImage($this->singleUseLicence());

        $result = $this->transfer([$source]);

        $this->assertHolder($source, self::RECEIVER_NAME, self::RECEIVER_ID);
        self::assertSame([(string) $source->getId()], $result->getTransferred());
    }

    public function testRepeatedTransferIsIdempotent(): void
    {
        $source = $this->createImage($this->singleUseLicence());
        $copy = $this->findImage($this->useFor($source, self::HOLDER_ID)->getImageFileId());

        $this->transfer([$copy]);
        $repeated = $this->transfer([$copy]);

        $this->assertHolder($source, self::RECEIVER_NAME, self::RECEIVER_ID);
        self::assertSame([(string) $copy->getId()], $repeated->getTransferred());
        self::assertCount(0, $repeated->getNotHeldByFrom());
    }

    public function testTransferArmsTheUsageCheck(): void
    {
        $source = $this->createImage($this->singleUseLicence());
        $source->getAssetAttributes()->setUsedByCheckAfter(null);
        $this->entityManager->flush();

        $this->transfer([$source]);

        $this->entityManager->clear();
        self::assertNotNull($this->findImage((string) $source->getId())->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testPhotoThatIsNotSingleUseGetsNoHolder(): void
    {
        $plain = $this->createImage($this->createLicence());

        $result = $this->transfer([$plain]);

        $this->assertHolder($plain, App::EMPTY_STRING, App::EMPTY_STRING);
        self::assertSame([], $result->getTransferred());
        self::assertCount(0, $result->getNotHeldByFrom());
    }

    public function testTransferWithNoKnownIdsIsANoOp(): void
    {
        $result = $this->transfer([]);

        self::assertSame([], $result->getTransferred());
        self::assertCount(0, $result->getNotHeldByFrom());
    }

    private function assertHolder(ImageFile $image, string $holderName, string $holderId): void
    {
        $this->entityManager->clear();
        $attributes = $this->findImage((string) $image->getId())->getAssetAttributes();

        self::assertSame($holderName, $attributes->getUsedByHolderName());
        self::assertSame($holderId, $attributes->getUsedByHolderId());
    }

    private function useFor(ImageFile $imageFile, string $holderId): ImageUseResultDto
    {
        $request = (new ImageUseRequestDto())
            ->setHolder((new ImageHolderDto())->setName(self::HOLDER_NAME)->setId($holderId))
            ->setItems(new ArrayCollection([
                (new ImageUseItemDto())->setImageFile($imageFile)->setTargetAssetLicence($this->createLicence()),
            ]));

        /** @var ImageUseResultDto $result */
        $result = $this->imageUseFacade->useImages($request)->getResults()->first();

        return $result;
    }

    /**
     * @param list<ImageFile> $imageFiles
     */
    private function transfer(array $imageFiles): ImageTransferResultDto
    {
        return $this->imageTransferFacade->transferImages(
            (new ImageTransferRequestDto())
                ->setFrom((new ImageHolderDto())->setName(self::HOLDER_NAME)->setId(self::HOLDER_ID))
                ->setTo((new ImageHolderDto())->setName(self::RECEIVER_NAME)->setId(self::RECEIVER_ID))
                ->setImageFileIds(new ArrayCollection($imageFiles))
        );
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }

    private function singleUseLicence(): AssetLicence
    {
        return $this->createLicence(directUseAllowed: false, singleUseEnforced: true);
    }

    private function createLicence(bool $directUseAllowed = true, bool $singleUseEnforced = false): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('transfer-test-' . ++$this->extIdSequence);
        $licence->getFlags()
            ->setDirectUseAllowed($directUseAllowed)
            ->setManualUploadAllowed(true)
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
