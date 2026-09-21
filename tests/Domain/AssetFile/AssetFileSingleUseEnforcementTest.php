<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetMetadataBulkManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseEnforcer;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;

final class AssetFileSingleUseEnforcementTest extends CoreDamKernelTestCase
{
    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetMetadataBulkManager $assetMetadataBulkManager;
    private AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->assetMetadataBulkManager = $this->getService(AssetMetadataBulkManager::class);
        $this->assetFileSingleUseEnforcer = $this->getService(AssetFileSingleUseEnforcer::class);
    }

    public function testCreateUnderEnforcedLicenceForcesSingleUse(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: true));
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public function testBecomingSingleUseJoinsTheUsageCheckPopulation(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: true));
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertNotNull(
            $this->findImage($imageId)->getAssetAttributes()->getUsedByCheckAfter(),
            'Without a check date the reconcile never asks the ext system who really holds this photo.',
        );
    }

    public function testCreateUnderPlainLicenceKeepsSingleUseOff(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertFalse($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public function testUpdateCannotSwitchSingleUseOffUnderEnforcedLicence(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: true));
        $imageId = (string) $image->getId();

        $dto = new ImageFileAdmDetailDto();
        $dto->getFlags()
            ->setPublic($image->getFlags()->isPublic())
            ->setSingleUse(false)
        ;
        $this->imageManager->updateImage($image, $dto);
        $this->entityManager->clear();

        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public function testBulkEditCannotSwitchSingleUseOffUnderEnforcedLicence(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: true));
        $imageId = (string) $image->getId();
        $asset = $image->getAsset();
        self::assertSame($image, $asset->getMainFile());

        $dto = FormProvidableMetadataBulkUpdateDto::getInstance($asset)
            ->setMainFileSingleUse(false);
        $this->assetMetadataBulkManager->updateFromMetadataBulkDto($asset, $dto);
        $this->entityManager->clear();

        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public function testBulkEditTurningSingleUseOnArmsTheUsageCheck(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        $asset = $image->getAsset();
        self::assertNull($image->getAssetAttributes()->getUsedByCheckAfter());

        $dto = FormProvidableMetadataBulkUpdateDto::getInstance($asset)
            ->setMainFileSingleUse(true);
        $this->assetMetadataBulkManager->updateFromMetadataBulkDto($asset, $dto);
        $this->entityManager->clear();

        $stored = $this->findImage($imageId);
        self::assertTrue($stored->getFlags()->isSingleUse());
        self::assertNotNull($stored->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testEnforceLicenceBackfillsFilesCreatedBeforeTheFlag(): void
    {
        $licence = $this->createLicence(singleUseEnforced: false);
        $image = $this->createImage($licence);
        $imageId = (string) $image->getId();
        self::assertFalse($image->getFlags()->isSingleUse());

        $licence->getFlags()->setSingleUseEnforced(true);
        $this->entityManager->flush();

        self::assertSame(1, $this->assetFileSingleUseEnforcer->enforceLicence($licence));
        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertSame(0, $this->assetFileSingleUseEnforcer->enforceLicence($this->findLicence($licence->getId())));
    }

    public function testFlippingSingleUseOnThroughTheAdminArmsTheUsageCheck(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        self::assertNull($image->getAssetAttributes()->getUsedByCheckAfter());

        $dto = new ImageFileAdmDetailDto();
        $dto->getFlags()
            ->setPublic($image->getFlags()->isPublic())
            ->setSingleUse(true)
        ;
        $this->imageManager->updateImage($image, $dto);
        $this->entityManager->clear();

        self::assertNotNull(
            $this->findImage($imageId)->getAssetAttributes()->getUsedByCheckAfter(),
            'A photo switched by hand reaches the enforcer with the flag already set, so only this call puts '
            . 'it into the reconcile population; without it the register never learns who holds it.',
        );
    }

    public function testRefusingTheSwitchAfterFirstUseKeepsItsOwnReasonInsteadOfARotateError(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false));
        $image->setFirstUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();
        $this->imageManager->setAssetFileSingleUseEnforcer($this->enforcedSingleUseEnforcer());

        $dto = new ImageFileAdmDetailDto();
        $dto->getFlags()
            ->setPublic($image->getFlags()->isPublic())
            ->setSingleUse(true)
        ;

        try {
            $this->getService(ImageFacade::class)->update($image, $dto);
            self::fail('The switch had to be refused.');
        } catch (ForbiddenOperationException $exception) {
            self::assertSame(ForbiddenOperationException::IMAGE_SINGLE_USE_AFTER_FIRST_USE, $exception->getDetail());
        }
    }

    private function enforcedSingleUseEnforcer(): AssetFileSingleUseEnforcer
    {
        return new AssetFileSingleUseEnforcer(
            $this->getService(AssetRepository::class),
            $this->entityManager,
            $this->getService(IndexManager::class),
            $this->getService(DamLogger::class),
            true,
        );
    }

    private function findLicence(int $licenceId): AssetLicence
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, $licenceId);

        return $licence;
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }

    private function createLicence(bool $singleUseEnforced): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('single-use-enforced-test-' . ++$this->extIdSequence);
        $licence->getFlags()->setSingleUseEnforced($singleUseEnforced);

        return $this->assetLicenceManager->create($licence);
    }

    private function createImage(AssetLicence $licence): ImageFile
    {
        $imageFile = $this->imageFactory->createFromUrl($licence, 'https://example.test/single-use.jpg');
        $this->assetFactory->createForAssetFile($imageFile, $licence);

        return $this->imageManager->create($imageFile);
    }
}
