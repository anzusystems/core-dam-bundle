<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileUsageReconcileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\ImageFileUsage;
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileUsageReconcileOutcome;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;

final class AssetFileUsageReconcilerTest extends CoreDamKernelTestCase
{
    use AssetFileGroupUsageReaderAnsweringTrait;

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
    private TestHandler $journal;

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
            $imageId => new ImageFileUsage(true, [self::holder(self::OTHER_HOLDER_ID)]),
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
            $imageId => new ImageFileUsage(true, [self::holder(), self::holder(self::OTHER_HOLDER_ID)]),
        ])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getConfirmed());
        self::assertSame(0, $result->getRewritten());
        self::assertSame(self::HOLDER_ID, $this->findImage($imageId)->getAssetAttributes()->getUsedByHolderId());
        self::assertTrue($this->journal->hasRecordThatContains(
            sprintf('Single use group %s is held by 2 holders at once', $imageId),
            Level::Warning,
        ));
    }

    #[DataProvider('groupAnswerDataProvider')]
    public function testTheWholeGroupIsSettledFromTheAnswersForTheRootAndItsCopy(
        ?ImageFileUsage $rootUsage,
        ?ImageFileUsage $copyUsage,
        AssetFileUsageReconcileOutcome $expectedOutcome,
        string $expectedHolderId,
    ): void {
        $root = $this->createClaimedImage();
        $copy = $this->createClaimedCopyOf($root);
        $this->makeCheckDue($root);
        $this->makeCheckDue($copy);
        $rootId = (string) $root->getId();
        $copyId = (string) $copy->getId();

        $result = $this->reconcilerAnswering(array_filter([
            $rootId => $rootUsage,
            $copyId => $copyUsage,
        ]))->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, match ($expectedOutcome) {
            AssetFileUsageReconcileOutcome::Confirmed => $result->getConfirmed(),
            AssetFileUsageReconcileOutcome::Rewritten => $result->getRewritten(),
            AssetFileUsageReconcileOutcome::Released => $result->getReleased(),
            AssetFileUsageReconcileOutcome::Unanswered => $result->getUnanswered(),
            AssetFileUsageReconcileOutcome::Skipped => $result->getSkipped(),
        });
        self::assertSame($expectedHolderId, $this->findImage($rootId)->getAssetAttributes()->getUsedByHolderId());
        self::assertSame($expectedHolderId, $this->findImage($copyId)->getAssetAttributes()->getUsedByHolderId());
    }

    public static function groupAnswerDataProvider(): array
    {
        return [
            'copy used by another holder, root not answered' => [
                'rootUsage' => null,
                'copyUsage' => new ImageFileUsage(true, [self::holder(self::OTHER_HOLDER_ID)]),
                'expectedOutcome' => AssetFileUsageReconcileOutcome::Rewritten,
                'expectedHolderId' => self::OTHER_HOLDER_ID,
            ],
            'root unused, copy not answered' => [
                'rootUsage' => new ImageFileUsage(false, []),
                'copyUsage' => null,
                'expectedOutcome' => AssetFileUsageReconcileOutcome::Unanswered,
                'expectedHolderId' => self::HOLDER_ID,
            ],
            'root and copy unused' => [
                'rootUsage' => new ImageFileUsage(false, []),
                'copyUsage' => new ImageFileUsage(false, []),
                'expectedOutcome' => AssetFileUsageReconcileOutcome::Released,
                'expectedHolderId' => App::EMPTY_STRING,
            ],
            'root and copy used by the same other holder' => [
                'rootUsage' => new ImageFileUsage(true, [self::holder(self::OTHER_HOLDER_ID)]),
                'copyUsage' => new ImageFileUsage(true, [self::holder(self::OTHER_HOLDER_ID)]),
                'expectedOutcome' => AssetFileUsageReconcileOutcome::Rewritten,
                'expectedHolderId' => self::OTHER_HOLDER_ID,
            ],
            'root and copy used by two different holders' => [
                'rootUsage' => new ImageFileUsage(true, [self::holder()]),
                'copyUsage' => new ImageFileUsage(true, [self::holder(self::OTHER_HOLDER_ID)]),
                'expectedOutcome' => AssetFileUsageReconcileOutcome::Confirmed,
                'expectedHolderId' => self::HOLDER_ID,
            ],
        ];
    }

    #[DataProvider('holderRecordedOnOneFileDataProvider')]
    public function testHolderRecordedOnOnlyOneFileOfTheGroupIsConfirmedWithoutARewrite(bool $holderOnRoot): void
    {
        $root = $this->createSingleUseImage();
        $copy = $this->createSingleUseImage($root);
        $this->claim($holderOnRoot ? $root : $copy);
        $this->makeCheckDue($root);
        $this->makeCheckDue($copy);

        $result = $this->reconcilerAnswering([
            (string) $root->getId() => new ImageFileUsage(true, [self::holder()]),
            (string) $copy->getId() => new ImageFileUsage(true, [self::holder()]),
        ])->reconcile(10);

        self::assertSame(1, $result->getConfirmed());
        self::assertSame(0, $result->getRewritten());
        self::assertFalse($this->journal->hasRecordThatContains('the ext system says otherwise', Level::Warning));
    }

    public static function holderRecordedOnOneFileDataProvider(): array
    {
        return [
            'holder recorded on the root only' => ['holderOnRoot' => true],
            'holder recorded on the copy only' => ['holderOnRoot' => false],
        ];
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

    /**
     * @param array<string, ImageFileUsage> $usage
     */
    private function reconcilerAnswering(array $usage): AssetFileUsageReconcileFacade
    {
        return new AssetFileUsageReconcileFacade(
            $this->assetFileRepository,
            $this->assetFileManager,
            $this->groupUsageReaderAnswering($usage),
            $this->journalingDamLogger(),
        );
    }

    private function journalingDamLogger(): DamLogger
    {
        $this->journal = new TestHandler();

        return $this->recordingDamLogger($this->journal, self::CMS_SLUG);
    }

    private function createClaimedImage(): ImageFile
    {
        return $this->claim($this->createSingleUseImage());
    }

    private function createClaimedCopyOf(ImageFile $root): ImageFile
    {
        return $this->claim($this->createSingleUseImage($root));
    }

    private function createSingleUseImage(?ImageFile $takenOverFrom = null): ImageFile
    {
        $licence = $this->createLicence();
        $image = $this->imageFactory->createFromUrl($licence, 'https://example.test/reconcile-' . $this->extIdSequence . '.jpg');
        $this->assetFactory->createForAssetFile($image, $licence);
        $this->imageManager->create($image);
        $image->getFlags()->setSingleUse(true);
        if ($takenOverFrom instanceof ImageFile) {
            $image->getAssetAttributes()->setTakenOverFromId((string) $takenOverFrom->getId());
        }
        $this->assetFileManager->flush();

        return $image;
    }

    private function claim(ImageFile $image): ImageFile
    {
        $this->assetFileManager->updateUsage($image, UsageClaim::fromHolder(self::holder()), usedByCheckAfter: AssetFileManager::nextUsageCheck());

        return $image;
    }

    private static function holder(string $id = self::HOLDER_ID): ImageHolderDto
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
