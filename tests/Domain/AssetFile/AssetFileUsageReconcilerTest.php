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
use AnzuSystems\CoreDamBundle\Model\Domain\Image\UsageClaim;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageHolderDto;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetFileUsageReconcilerTest extends CoreDamKernelTestCase
{
    private const string CMS_SLUG = 'cms';
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '01994000-0000-7000-8000-000000000001';

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

        $result = $this->reconcilerAnswering([$imageId => false])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getReleased());
        $reloaded = $this->findImage($imageId);
        self::assertSame(App::EMPTY_STRING, $reloaded->getAssetAttributes()->getUsedByHolderName());
        self::assertNull($reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testClaimTheExtSystemStillPointsAtKeepsItsHolderAndIsNotCheckedAgain(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();
        $this->makeCheckDue($image);

        $result = $this->reconcilerAnswering([$imageId => true])->reconcile(10);
        $this->entityManager->clear();

        self::assertSame(1, $result->getConfirmed());
        $reloaded = $this->findImage($imageId);
        self::assertSame(self::HOLDER_ID, $reloaded->getAssetAttributes()->getUsedByHolderId());
        self::assertNull($reloaded->getAssetAttributes()->getUsedByCheckAfter());
    }

    public function testFreshClaimIsLeftAloneUntilItsCheckComesDue(): void
    {
        $image = $this->createClaimedImage();
        $imageId = (string) $image->getId();

        $result = $this->reconcilerAnswering([$imageId => false])->reconcile(10);
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
     * @param array<string, bool> $usage
     */
    private function reconcilerAnswering(array $usage): AssetFileUsageReconciler
    {
        $callback = self::createStub(ExtSystemCallbackInterface::class);
        $callback->method('isImageFileUsedBulk')->willReturn($usage);

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

        $holder = new ImageHolderDto()
            ->setName(self::HOLDER_NAME)
            ->setId(self::HOLDER_ID)
        ;

        $this->assetFileManager->updateUsage($image, UsageClaim::fromHolder($holder));

        return $image;
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
