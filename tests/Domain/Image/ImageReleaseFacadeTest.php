<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageReleaseFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageReleaseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class ImageReleaseFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000002';

    private ImageReleaseFacade $imageReleaseFacade;
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
        $this->imageReleaseFacade = $this->getService(ImageReleaseFacade::class);
        $this->imageUseFacade = $this->getService(ImageUseFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->facadeProvider = $this->getService(AssetFileStatusFacadeProvider::class);
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    public function testHolderReleaseFreesTheWholeGroup(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $target = $this->createLicence();
        $copy = $this->findImage($this->useOne($source, $target)->getImageFileId());

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source]));

        $this->assertFree($source);
        $this->assertFree($copy);
    }

    public function testReleaseLeavesTheGroupToBeVerifiedOnce(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $this->useOne($source, $this->createLicence());

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source]));

        $this->assertFree($source);
        $this->entityManager->clear();
        self::assertNotNull(
            $this->findImage((string) $source->getId())->getAssetAttributes()->getUsedByCheckAfter(),
            'A release decided by the ext system is checked once more, so a wrong one heals instead of '
            . 'leaving the photo free with nothing left to notice it.',
        );
    }

    public function testReleaseByAForeignHolderIsANoOp(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $this->useOne($source, $this->createLicence());

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source], holderId: self::OTHER_HOLDER_ID));

        $this->assertHolder($source, self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testReleaseOfAFreePhotoIsANoOp(): void
    {
        $source = $this->createImage($this->createLicence());

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source]));

        $this->assertFree($source);
    }

    public function testRepeatedReleaseIsIdempotent(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $this->useOne($source, $this->createLicence());

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source]));
        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$source]));

        $this->assertFree($source);
    }

    public function testBatchReleasesOnlyTheGroupsHeldByThisHolder(): void
    {
        $ownSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $this->useOne($ownSource, $this->createLicence());

        $foreignSource = $this->createImage($this->createLicence(directUseAllowed: false, singleUseEnforced: true));
        $this->useImageFor($foreignSource, $this->createLicence(), self::OTHER_HOLDER_ID);

        $this->imageReleaseFacade->releaseImages($this->releaseRequest([$ownSource, $foreignSource]));

        $this->assertFree($ownSource);
        $this->assertHolder($foreignSource, self::HOLDER_NAME, self::OTHER_HOLDER_ID);
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

    private function useOne(ImageFile $imageFile, AssetLicence $targetLicence): ImageUseResultDto
    {
        return $this->useImageFor($imageFile, $targetLicence, self::HOLDER_ID);
    }

    private function useImageFor(ImageFile $imageFile, AssetLicence $targetLicence, string $holderId): ImageUseResultDto
    {
        $request = (new ImageUseRequestDto())
            ->setHolder((new ImageHolderDto())->setName(self::HOLDER_NAME)->setId($holderId))
            ->setItems(new ArrayCollection([
                (new ImageUseItemDto())->setImageFile($imageFile)->setTargetAssetLicence($targetLicence),
            ]));

        /** @var ImageUseResultDto $result */
        $result = $this->imageUseFacade->useImages($request)->getResults()->first();

        return $result;
    }

    /**
     * @param list<ImageFile> $imageFiles
     */
    private function releaseRequest(array $imageFiles, string $holderId = self::HOLDER_ID): ImageReleaseRequestDto
    {
        return (new ImageReleaseRequestDto())
            ->setHolder((new ImageHolderDto())->setName(self::HOLDER_NAME)->setId($holderId))
            ->setImageFileIds(new ArrayCollection($imageFiles));
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }

    private function createLicence(
        bool $directUseAllowed = true,
        bool $singleUseEnforced = false,
        int $extSystemId = ExtSystemFixtures::ID_CMS,
    ): AssetLicence {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, $extSystemId);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('release-test-' . ++$this->extIdSequence);
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
