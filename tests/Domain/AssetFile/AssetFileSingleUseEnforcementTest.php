<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Command\AssetLicenceEnforceSingleUseCommand;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetManager;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetMetadataBulkManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileInternalRuleEvaluator;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileSingleUseEnforcer;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceSingleUseFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
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
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetFileSingleUseEnforcementTest extends CoreDamKernelTestCase
{
    private const string CMS_SLUG = 'cms';
    private const string SWITCH_AFTER_FIRST_USE_LOG = 'switched to single use after its first use';

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

    #[DataProvider('createUnderLicenceDataProvider')]
    public function testCreateTakesSingleUseFromTheLicence(bool $singleUseEnforced, bool $expectedSingleUse): void
    {
        $image = $this->createImage($this->createLicence(singleUseEnforced: $singleUseEnforced));
        $imageId = (string) $image->getId();
        $this->entityManager->clear();

        self::assertSame($expectedSingleUse, $this->findImage($imageId)->getFlags()->isSingleUse());
    }

    public static function createUnderLicenceDataProvider(): array
    {
        return [
            'licence enforcing single use' => [
                'singleUseEnforced' => true,
                'expectedSingleUse' => true,
            ],
            'plain licence' => [
                'singleUseEnforced' => false,
                'expectedSingleUse' => false,
            ],
        ];
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
        $this->enforceSingleUseAfterUpload($licence);

        $first = $this->runEnforceSingleUse((int) $licence->getId(), Command::SUCCESS);
        $second = $this->runEnforceSingleUse((int) $licence->getId(), Command::SUCCESS);

        self::assertStringContainsString(sprintf('Switched 1 asset file(s) of licence %d', $licence->getId()), $first);
        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertStringContainsString(sprintf('Switched 0 asset file(s) of licence %d', $licence->getId()), $second);
    }

    #[DataProvider('enforceSingleUseRefusalDataProvider')]
    public function testEnforceSingleUseCommandRefusesALicenceItCannotEnforce(int $licenceId, string $expectedError): void
    {
        $display = $this->runEnforceSingleUse($licenceId, Command::FAILURE);

        self::assertStringContainsString($expectedError, $display);
    }

    public static function enforceSingleUseRefusalDataProvider(): array
    {
        return [
            'unknown licence' => [
                'licenceId' => App::ZERO,
                'expectedError' => 'Asset licence not found',
            ],
            'licence without single use enforcement' => [
                'licenceId' => AssetLicenceFixtures::DEFAULT_LICENCE_ID,
                'expectedError' => 'Asset licence does not enforce single use',
            ],
        ];
    }

    public function testEnforceLicenceAnnouncesTheSwitchedFilesToTheExtSystem(): void
    {
        $licence = $this->createLicence(singleUseEnforced: false);
        $image = $this->createImage($licence);
        $imageId = (string) $image->getId();
        $this->enforceSingleUseAfterUpload($licence);

        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::once())
            ->method('notifyImagesChanged')
            ->with(self::callback(
                static fn (Collection $images): bool => [$imageId] === array_map(
                    static fn (ImageFile $imageFile): string => (string) $imageFile->getId(),
                    $images->getValues(),
                ),
            ));

        self::assertSame(1, $this->licenceFacadeAnnouncingTo($callback)->enforce($licence));
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
        $image = $this->createUsedImage($this->createLicence(singleUseEnforced: false));
        $this->imageManager->setAssetFileSingleUseEnforcer($this->singleUseEnforcer(enforced: true));

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

    public function testBulkEditRefusesTheSwitchAfterFirstUse(): void
    {
        $image = $this->createUsedImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        $asset = $image->getAsset();

        $dto = FormProvidableMetadataBulkUpdateDto::getInstance($asset)
            ->setMainFileSingleUse(true);
        $journal = new TestHandler();

        try {
            $this->assetMetadataBulkManagerWith($this->singleUseEnforcer(enforced: true, journal: $journal))
                ->updateFromMetadataBulkDto($asset, $dto);
            self::fail('The switch had to be refused.');
        } catch (ForbiddenOperationException $exception) {
            self::assertSame(ForbiddenOperationException::IMAGE_SINGLE_USE_AFTER_FIRST_USE, $exception->getDetail());
        }
        $this->entityManager->clear();

        self::assertFalse($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertFalse($journal->hasWarningThatContains(self::SWITCH_AFTER_FIRST_USE_LOG));
    }

    public function testBulkEditSwitchAfterFirstUsePassesInSoftMode(): void
    {
        $image = $this->createUsedImage($this->createLicence(singleUseEnforced: false));
        $imageId = (string) $image->getId();
        $asset = $image->getAsset();

        $dto = FormProvidableMetadataBulkUpdateDto::getInstance($asset)
            ->setMainFileSingleUse(true);
        $journal = new TestHandler();
        $this->assetMetadataBulkManagerWith($this->singleUseEnforcer(enforced: false, journal: $journal))
            ->updateFromMetadataBulkDto($asset, $dto);
        $this->entityManager->clear();

        self::assertTrue($this->findImage($imageId)->getFlags()->isSingleUse());
        self::assertTrue($journal->hasRecordThatContains(
            sprintf('Asset file %s %s', $imageId, self::SWITCH_AFTER_FIRST_USE_LOG),
            Level::Warning,
        ));
    }

    private function runEnforceSingleUse(int $licenceId, int $expectedExitCode): string
    {
        $command = new AssetLicenceEnforceSingleUseCommand(
            $this->getService(AssetLicenceRepository::class),
            $this->getService(AssetLicenceSingleUseFacade::class),
        );
        $tester = new CommandTester(new Command(code: $command));
        $exitCode = $tester->execute(['licence_id' => $licenceId]);
        $display = $tester->getDisplay();
        self::assertSame($expectedExitCode, $exitCode, $display);

        return $display;
    }

    private function enforceSingleUseAfterUpload(AssetLicence $licence): void
    {
        $licence->getFlags()->setSingleUseEnforced(true);
        $this->entityManager->flush();
    }

    private function licenceFacadeAnnouncingTo(ExtSystemCallbackInterface $callback): AssetLicenceSingleUseFacade
    {
        $facade = new ExtSystemCallbackFacade(
            new ServiceLocator([
                self::CMS_SLUG => static fn (): ExtSystemCallbackInterface => $callback,
            ]),
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        );

        return new AssetLicenceSingleUseFacade(
            $this->getService(AssetRepository::class),
            $this->getService(AssetFileManager::class),
            $this->getService(IndexManager::class),
            $facade,
            $this->assetFileSingleUseEnforcer,
        );
    }

    private function singleUseEnforcer(bool $enforced, ?TestHandler $journal = null): AssetFileSingleUseEnforcer
    {
        $damLogger = $this->getService(DamLogger::class);
        if ($journal instanceof TestHandler) {
            $damLogger = $this->recordingDamLogger($journal);
        }

        return new AssetFileSingleUseEnforcer($damLogger, singleUseEnforced: $enforced);
    }

    private function assetMetadataBulkManagerWith(AssetFileSingleUseEnforcer $assetFileSingleUseEnforcer): AssetMetadataBulkManager
    {
        return new AssetMetadataBulkManager(
            $this->getService(AssetManager::class),
            $this->getService(AssetMetadataManager::class),
            $this->getService(AuthorProvider::class),
            $this->getService(AssetFileInternalRuleEvaluator::class),
            $assetFileSingleUseEnforcer,
        );
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

    private function createUsedImage(AssetLicence $licence): ImageFile
    {
        $image = $this->createImage($licence);
        $image->setFirstUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $image;
    }

    private function createImage(AssetLicence $licence): ImageFile
    {
        $imageFile = $this->imageFactory->createFromUrl($licence, 'https://example.test/single-use.jpg');
        $this->assetFactory->createForAssetFile($imageFile, $licence);

        return $this->imageManager->create($imageFile);
    }
}
