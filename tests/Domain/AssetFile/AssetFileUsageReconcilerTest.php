<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileUsageReconciler;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\ImageFileUsage;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetFileUsageReconcilerTest extends CoreDamKernelTestCase
{
    private const string CMS_SLUG = 'cms';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '01994000-0000-7000-8000-000000000001';
    private const string OTHER_HOLDER_ID = '01994000-0000-7000-8000-000000000002';

    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private AssetFileManager $assetFileManager;
    private AssetFileRepository $assetFileRepository;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
        $this->assetFileManager = $this->getService(AssetFileManager::class);
        $this->assetFileRepository = $this->getService(AssetFileRepository::class);
    }

    public function testClaimTheExtSystemNoLongerPointsAtIsReleased(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([$imageId => new ImageFileUsage(false, [])])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getReleased());
        $reloaded = $this->findImage($imageId);
        self::assertSame(App::EMPTY_STRING, $reloaded->getAssetAttributes()->getUsedByHolderName());
        self::assertNull($reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    /**
     * @param list<ImageHolderDto>|null $holders
     */
    #[DataProvider('confirmedWithoutARewriteDataProvider')]
    public function testClaimTheExtSystemStillPointsAtKeepsItsHolderAndIsCheckedAgainTomorrow(?array $holders): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([$imageId => new ImageFileUsage(true, $holders)])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getConfirmed());
        self::assertSame(0, $result->getRewritten());
        $reloaded = $this->findImage($imageId);
        self::assertSame(self::HOLDER_ID, $reloaded->getAssetAttributes()->getUsedByHolderId());
        self::assertGreaterThan(new DateTimeImmutable('+23 hours'), $reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    public static function confirmedWithoutARewriteDataProvider(): array
    {
        return [
            'holders not reported by this ext system' => ['holders' => null],
            'holders reported, none can hold it' => ['holders' => []],
        ];
    }

    public function testGroupTheExtSystemDidNotAnswerForStaysHeldAndIsAskedAboutAgain(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getUnanswered());
        self::assertSame(0, $result->getReleased());
        $reloaded = $this->findImage($imageId);
        self::assertSame(self::HOLDER_ID, $reloaded->getAssetAttributes()->getUsedByHolderId());
        self::assertGreaterThan(new DateTimeImmutable('+23 hours'), $reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testHolderTheExtSystemReportsIsWrittenBack(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([
            $imageId => new ImageFileUsage(true, [$this->holder(self::OTHER_HOLDER_ID)]),
        ])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getRewritten());
        $reloaded = $this->findImage($imageId);
        self::assertSame(self::OTHER_HOLDER_ID, $reloaded->getAssetAttributes()->getUsedByHolderId());
        self::assertGreaterThan(new DateTimeImmutable('+23 hours'), $reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testTwoHoldersLeaveTheRecordedOneAloneAndAreReported(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([
            $imageId => new ImageFileUsage(true, [$this->holder(), $this->holder(self::OTHER_HOLDER_ID)]),
        ])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getConfirmed());
        self::assertSame(0, $result->getRewritten());
        self::assertSame(self::HOLDER_ID, $this->findImage($imageId)->getAssetAttributes()->getUsedByHolderId());
    }

    public function testReClaimByTheSameHolderPushesTheCheckForward(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $this->assetFileManager->updateUsage($image, UsageClaim::fromHolder($this->holder()));
        $this->entityManager->clear();

        self::assertGreaterThan(
            new DateTimeImmutable(),
            $this->findImage($imageId)->getAssetAttributes()->getUsedByCheckAfter(),
        );
    }

    public function testFreshClaimIsLeftAloneUntilItsCheckComesDue(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();

        $result = $this->reconcilerAnswering([$imageId => new ImageFileUsage(false, [])])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(0, $result->getChecked());
        self::assertSame(self::HOLDER_ID, $this->findImage($imageId)->getAssetAttributes()->getUsedByHolderId());
    }

    public function testReleaseClearsTheCheckSoNothingIsAskedAboutAFreePhoto(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();

        $this->assetFileManager->updateUsage($image, UsageClaim::released());
        $this->entityManager->clear();

        self::assertNull($this->findImage($imageId)->getAssetAttributes()->getUsedByCheckAfter());
    }

    /**
     * @param array<string, ImageFileUsage> $usage
     */
    private function reconcilerAnswering(array $usage): AssetFileUsageReconciler
    {
        $callback = self::createStub(ExtSystemCallbackInterface::class);
        $callback->method('resolveImageFileUsage')->willReturn($usage);

        $facade = new ExtSystemCallbackFacade(
            new ServiceLocator([
                self::CMS_SLUG => static fn (): ExtSystemCallbackInterface => $callback,
            ]),
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        );

        return new AssetFileUsageReconciler(
            $this->assetFileRepository,
            $this->assetFileManager,
            $facade,
            $this->getService(DamLogger::class),
        );
    }

    private function createClaimedImage(): ImageFile
    {
        $licence = $this->createLicence();
        $image = $this->imageFactory->createFromUrl($licence, 'https://example.test/reconcile.jpg');
        $this->assetFactory->createForAssetFile($image, $licence);
        $this->imageManager->create($image);
        $image->getFlags()->setSingleUse(true);

        $this->assetFileManager->updateUsage($image, UsageClaim::fromHolder($this->holder()));

        return $image;
    }

    private function holder(string $id = self::HOLDER_ID): ImageHolderDto
    {
        return new ImageHolderDto()
            ->setName(self::HOLDER_NAME)
            ->setId($id)
        ;
    }

    private function makeCheckDue(ImageFile $image): void
    {
        $image->getAssetAttributes()->setUsedByCheckAfter(new DateTimeImmutable('-1 hour'));
        $this->assetFileManager->flush();
    }

    private function createLicence(): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);

        return $this->assetLicenceManager->create(
            new AssetLicence()
                ->setExtId('reconcile-' . ++$this->extIdSequence)
                ->setExtSystem($extSystem)
        );
    }

    private function findImage(string $id): ImageFile
    {
        $image = $this->assetFileRepository->find($id);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image;
    }
}
