<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetMetadataBulkManager;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AssetFileSingleUseEnforcementTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private const string LICENCE_COMMAND = 'anzu-dam:asset-licence:enforce-single-use';
    private const string SOURCE_COMMAND = 'anzu-dam:author:enforce-single-use';

    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetMetadataBulkManager $assetMetadataBulkManager;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->assetMetadataBulkManager = $this->getService(AssetMetadataBulkManager::class);
    }

    #[DataProvider('createDataProvider')]
    public function testCreateAppliesTheLicenceAndSourceFlags(bool $licenceEnforced, ?bool $sourceEnforced, bool $singleUse): void
    {
        $source = match ($sourceEnforced) {
            true => $this->createEnforcedSource(),
            false => $this->createSource('Plain source'),
            null => null,
        };
        $image = $this->createImage($this->createLicence(singleUseEnforced: $licenceEnforced), $source);
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertSame($singleUse, $this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public static function createDataProvider(): array
    {
        return [
            'enforced licence' => [true, null, true],
            'plain licence' => [false, null, false],
            'enforced source' => [false, true, true],
            'plain source' => [false, false, false],
        ];
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

    public function testEnforceLicenceBackfillsFilesCreatedBeforeTheFlag(): void
    {
        $licence = $this->createLicence(singleUseEnforced: false);
        $image = $this->createImage($licence);
        $imageId = (string) $image->getId();
        self::assertFalse($image->getFlags()->isSingleUse());

        $licence->getFlags()->setSingleUseEnforced(true);
        $this->entityManager->flush();

        self::assertStringContainsString('Switched 1 asset file(s)', $this->enforceByCommand(self::LICENCE_COMMAND, ['licence_id' => $licence->getId()]));
        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertStringContainsString('Switched 0 asset file(s)', $this->enforceByCommand(self::LICENCE_COMMAND, ['licence_id' => $licence->getId()]));
    }

    public function testCopyIntoEnforcedLicenceKeepsSingleUse(): void
    {
        $origin = $this->findImage(ImageFixtures::IMAGE_ID_1_1);
        $origin->getFlags()->setSingleUse(false);
        $this->entityManager->flush();
        $target = $this->createImage($this->createLicence(singleUseEnforced: true));

        $this->getService(ImageCopyFacade::class)->copyAssetFiles($origin->getAsset(), $target->getAsset());

        self::assertTrue($target->getFlags()->isSingleUse());
    }

    public function testBulkEditAddingEnforcedSourceSwitchesSingleUse(): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        $asset = $image->getAsset();
        self::assertFalse($image->getFlags()->isSingleUse());

        $source = $this->createEnforcedSource();
        $dto = FormProvidableMetadataBulkUpdateDto::getInstance($asset)
            ->setSources(new ArrayCollection([(string) $source->getId() => $source]));
        $this->assetMetadataBulkManager->updateFromMetadataBulkDto($asset, $dto);
        $this->entityManager->clear();

        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public function testEnforceSourceBackfillsFilesCreatedBeforeTheFlag(): void
    {
        $source = $this->createSource('Source enforced later');
        $image = $this->createImage($this->createLicence(singleUseEnforced: false), $source);
        $imageId = (string) $image->getId();
        self::assertFalse($image->getFlags()->isSingleUse());

        $source->getFlags()->setSingleUseEnforced(true);
        $this->entityManager->flush();

        self::assertStringContainsString('Switched 1 asset file(s)', $this->enforceByCommand(self::SOURCE_COMMAND, ['author_id' => (string) $source->getId()]));
        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertStringContainsString('Switched 0 asset file(s)', $this->enforceByCommand(self::SOURCE_COMMAND, ['author_id' => (string) $source->getId()]));
    }

    #[DataProvider('metadataDataProvider')]
    public function testCreateAppliesTheMetadataPattern(?string $credit, ?string $source, bool $singleUse): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: false), exifData: ['Credit' => $credit, 'Source' => $source]);
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertSame($singleUse, $this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public static function metadataDataProvider(): array
    {
        return [
            'AP Photo credit' => ['AP Photo/Ryan Murphy', 'AP', true],
            'Pool via AP credit' => ['Aaron Chown/Pool PA via AP', 'Pool PA', true],
            'TASR with source AP' => ['TASR', 'AP', true],
            'AP job number in source' => ['AP Photo/Ryan Murphy', 'FR172324 AP', true],
            'Invision source' => [null, 'Invision', true],
            'AP after a comma' => ['TASR,AP', null, true],
            'TASR only' => ['TASR', 'TASR', false],
            'AP inside a word' => ['APA', 'Japan Times', false],
            'No metadata' => [null, null, false],
        ];
    }

    public function testEnforceLicenceBackfillsFilesWithMatchingMetadata(): void
    {
        $licence = $this->createLicence(singleUseEnforced: false);
        $image = $this->createImage($licence);
        $imageId = (string) $image->getId();
        $image->getMetadata()->setExifData(['Credit' => 'TASR/AP', 'Source' => 'AP']);
        $this->entityManager->flush();
        self::assertFalse($image->getFlags()->isSingleUse());

        self::assertStringContainsString('1 asset file(s) of licence', $this->enforceByCommand(self::LICENCE_COMMAND, ['licence_id' => $licence->getId(), '--dry-run' => true]));
        self::assertFalse($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertStringContainsString('Switched 1 asset file(s)', $this->enforceByCommand(self::LICENCE_COMMAND, ['licence_id' => $licence->getId()]));
        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
    }

    /**
     * @param array<string, bool|int|string> $input
     */
    private function enforceByCommand(string $command, array $input): string
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find($command));
        $code = $commandTester->execute($input);
        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        self::assertSame(Command::SUCCESS, $code, $display);
        $this->entityManager->clear();

        return $display;
    }

    private function createEnforcedSource(): Author
    {
        $source = $this->createSource('Enforced source ' . ++$this->extIdSequence);
        $source->getFlags()->setSingleUseEnforced(true);
        $this->entityManager->flush();

        return $source;
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

    private function createImage(AssetLicence $licence, ?Author $source = null, array $exifData = []): ImageFile
    {
        $imageFile = $this->imageFactory->createFromUrl($licence, 'https://example.test/single-use.jpg');
        $imageFile->getMetadata()->setExifData($exifData);
        $asset = $this->assetFactory->createForAssetFile($imageFile, $licence);
        if ($source instanceof Author) {
            $asset->addSource($source);
        }

        return $this->imageManager->create($imageFile);
    }
}
