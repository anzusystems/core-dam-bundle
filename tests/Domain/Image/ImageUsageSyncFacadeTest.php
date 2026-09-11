<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Image;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageTakeOverFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUsageSyncFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTakeOverRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageSyncDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final class ImageUsageSyncFacadeTest extends CoreDamKernelTestCase
{
    private const string SOURCE_FILE_NAME = 'text_image_192x108.jpg';
    private const string SCOPE_NAME = 'gallery';
    private const string OTHER_SCOPE_NAME = 'articleKindStandard';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '2f8b0f1e-0000-4000-8000-000000000002';

    private ImageUsageSyncFacade $imageUsageSyncFacade;
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
        $this->imageUsageSyncFacade = $this->getService(ImageUsageSyncFacade::class);
        $this->imageTakeOverFacade = $this->getService(ImageTakeOverFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->facadeProvider = $this->getService(AssetFileStatusFacadeProvider::class);
        $this->fileSystemProvider = $this->getService(FileSystemProvider::class);
    }

    public function testFreeImageIsClaimedByTheScope(): void
    {
        $image = $this->createImage($this->createLicence());

        $result = $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $image->getId()]));

        self::assertCount(App::ZERO, $result->getConflicts());
        $this->assertUsage($image, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testImageHeldByAnotherScopeIsReportedAsConflict(): void
    {
        $image = $this->createImage($this->createLicence());
        $damId = (string) $image->getId();
        $this->imageUsageSyncFacade->sync(
            $this->syncDto('10', [$damId], scopeName: self::OTHER_SCOPE_NAME, holderId: self::OTHER_HOLDER_ID)
        );

        $result = $this->imageUsageSyncFacade->sync($this->syncDto('20', [$damId]));

        self::assertCount(1, $result->getConflicts());
        $conflict = $result->getConflicts()->first();
        self::assertInstanceOf(ImageUsageConflictDto::class, $conflict);
        self::assertSame($damId, $conflict->getDamId());
        self::assertSame(self::HOLDER_NAME, $conflict->getResourceName());
        self::assertSame(self::OTHER_HOLDER_ID, $conflict->getResourceId());
        // A conflicting group is left exactly as it was, holder and scope alike.
        $this->assertUsage($image, self::OTHER_SCOPE_NAME, '10', self::HOLDER_NAME, self::OTHER_HOLDER_ID);
    }

    public function testRepeatedSyncOfTheSameScopeIsANoOp(): void
    {
        $image = $this->createImage($this->createLicence());
        $damId = (string) $image->getId();
        $this->imageUsageSyncFacade->sync($this->syncDto('10', [$damId]));

        $result = $this->imageUsageSyncFacade->sync($this->syncDto('10', [$damId]));

        self::assertCount(App::ZERO, $result->getConflicts());
        $this->assertUsage($image, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testImageDroppedFromTheRequestIsReleased(): void
    {
        // Separate licences on purpose: the same bytes twice in one licence would be stored as a duplicate.
        $kept = $this->createImage($this->createLicence());
        $dropped = $this->createImage($this->createLicence());
        $this->imageUsageSyncFacade->sync(
            $this->syncDto('10', [(string) $kept->getId(), (string) $dropped->getId()])
        );

        $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $kept->getId()]));

        $this->assertUsage($kept, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertFree($dropped);
    }

    public function testEmptyRequestReleasesTheWholeScope(): void
    {
        $image = $this->createImage($this->createLicence());
        $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $image->getId()]));

        $this->imageUsageSyncFacade->sync($this->syncDto('10', []));

        $this->assertFree($image);
    }

    public function testClaimingTheTakeOverRootAlsoClaimsItsCopy(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $copy = $this->takeOver($source);

        $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $source->getId()]));

        $this->assertUsage($source, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertUsage($copy, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testClaimingATakeOverCopyAlsoClaimsItsRoot(): void
    {
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $copy = $this->takeOver($source);

        $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $copy->getId()]));

        $this->assertUsage($source, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertUsage($copy, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
    }

    public function testEmptyHolderKeepsTheScopeWithoutAHolder(): void
    {
        $image = $this->createImage($this->createLicence());

        $this->imageUsageSyncFacade->sync(
            $this->syncDto('10', [(string) $image->getId()], holderName: App::EMPTY_STRING, holderId: App::EMPTY_STRING)
        );

        $this->assertUsage($image, self::SCOPE_NAME, '10', App::EMPTY_STRING, App::EMPTY_STRING);
    }

    public function testFirstUsedAtIsNeverTouched(): void
    {
        $image = $this->createImage($this->createLicence());
        $firstUsedAt = new DateTimeImmutable('2020-01-02 03:04:05');
        $image->setFirstUsedAt($firstUsedAt);
        $this->imageManager->updateExisting($image);
        $damId = (string) $image->getId();

        $this->imageUsageSyncFacade->sync($this->syncDto('10', [$damId]));
        $this->imageUsageSyncFacade->sync($this->syncDto('10', []));

        $this->entityManager->clear();
        self::assertSame($firstUsedAt->getTimestamp(), $this->findImage($damId)->getFirstUsedAt()?->getTimestamp());
    }

    /**
     * A take-over that adopts an already used file ({@see ImageTakeOverFacade::reuseExisting()}) can put two
     * scopes into one group. The group then conflicts, and the file this scope does hold must survive it —
     * releasing it would free the photo for everyone while the conflict is still unresolved.
     */
    public function testConflictingGroupKeepsTheFileThisScopeHolds(): void
    {
        $targetLicence = $this->createLicence();
        $source = $this->createImage($this->createLicence(directUseAllowed: false));
        $existing = $this->createImage($targetLicence);

        $this->imageUsageSyncFacade->sync(
            $this->syncDto('99', [(string) $existing->getId()], scopeName: self::OTHER_SCOPE_NAME, holderId: self::OTHER_HOLDER_ID)
        );
        $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $source->getId()]));

        $adopted = $this->takeOverTo($source, $targetLicence);
        self::assertSame((string) $existing->getId(), (string) $adopted->getId());

        $result = $this->imageUsageSyncFacade->sync($this->syncDto('10', [(string) $source->getId()]));

        self::assertCount(1, $result->getConflicts());
        $this->assertUsage($source, self::SCOPE_NAME, '10', self::HOLDER_NAME, self::HOLDER_ID);
        $this->assertUsage($existing, self::OTHER_SCOPE_NAME, '99', self::HOLDER_NAME, self::OTHER_HOLDER_ID);
    }

    private function assertUsage(
        ImageFile $image,
        string $scopeName,
        string $scopeId,
        string $holderName,
        string $holderId,
    ): void {
        // Cleared first: read through the identity map these assertions would pass on the in-memory entity
        // even if the flush wrote nothing.
        $this->entityManager->clear();
        $attributes = $this->findImage((string) $image->getId())->getAssetAttributes();

        self::assertSame($scopeName, $attributes->getUsedByScopeName());
        self::assertSame($scopeId, $attributes->getUsedByScopeId());
        self::assertSame($holderName, $attributes->getUsedByResourceName());
        self::assertSame($holderId, $attributes->getUsedByResourceId());
    }

    private function assertFree(ImageFile $image): void
    {
        $this->assertUsage($image, App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING, App::EMPTY_STRING);
    }

    /**
     * @param string[] $damIds
     */
    private function syncDto(
        string $scopeId,
        array $damIds,
        string $scopeName = self::SCOPE_NAME,
        string $holderName = self::HOLDER_NAME,
        string $holderId = self::HOLDER_ID,
    ): ImageUsageSyncDto {
        return (new ImageUsageSyncDto())
            ->setScopeResourceName($scopeName)
            ->setScopeResourceId($scopeId)
            ->setHolderResourceName($holderName)
            ->setHolderResourceId($holderId)
            ->setDamIds(new ArrayCollection($damIds));
    }

    private function takeOver(ImageFile $source): ImageFile
    {
        return $this->takeOverTo($source, $this->createLicence());
    }

    private function takeOverTo(ImageFile $source, AssetLicence $targetLicence): ImageFile
    {
        return $this->findImage(
            $this->imageTakeOverFacade->takeOver(
                (new ImageTakeOverRequestDto())
                    ->setImageFile($source)
                    ->setTargetAssetLicence($targetLicence)
            )->getImageFileId()
        );
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }

    private function createLicence(
        bool $directUseAllowed = true,
        int $extSystemId = ExtSystemFixtures::ID_CMS,
    ): AssetLicence {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, $extSystemId);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('usage-sync-test-' . ++$this->extIdSequence);
        $licence->getFlags()
            ->setDirectUseAllowed($directUseAllowed)
            ->setManualUploadAllowed(true)
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
