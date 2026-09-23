<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFirstUseFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class AssetFileFirstUseFacadeTest extends CoreDamKernelTestCase
{
    private const string USED_AT = '2026-03-01 10:15:00';
    private const string EARLIER_USED_AT = '2020-05-04 08:30:00';

    private AssetFileFirstUseFacade $firstUseFacade;
    private AssetLicenceManager $assetLicenceManager;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firstUseFacade = $this->getService(AssetFileFirstUseFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
    }

    public function testFirstUseOfATakeOverAlsoStampsTheOriginal(): void
    {
        $original = $this->createImage();
        $takeOver = $this->createTakeOverOf($original);

        $this->firstUseFacade->record([$takeOver], new DateTimeImmutable(self::USED_AT));
        $this->entityManager->clear();

        self::assertSame(self::USED_AT, $this->reloadFirstUsedAt($takeOver));
        // First use belongs to the photo, not to the file the article happens to reference.
        self::assertSame(self::USED_AT, $this->reloadFirstUsedAt($original));
    }

    public function testOriginalKeepsItsEarlierFirstUse(): void
    {
        $original = $this->createImage();
        $original->setFirstUsedAt(new DateTimeImmutable(self::EARLIER_USED_AT));
        $takeOver = $this->createTakeOverOf($original);

        $this->firstUseFacade->record([$takeOver], new DateTimeImmutable(self::USED_AT));
        $this->entityManager->clear();

        self::assertSame(self::EARLIER_USED_AT, $this->reloadFirstUsedAt($original));
    }

    public function testTakeOverOfAnOriginalDeletedByRetentionIsStillStamped(): void
    {
        $takeOver = $this->createImage();
        $takeOver->getAssetAttributes()->setTakenOverFromId(Uuid::v7()->toRfc4122());
        $this->entityManager->flush();

        $this->firstUseFacade->record([$takeOver], new DateTimeImmutable(self::USED_AT));
        $this->entityManager->clear();

        self::assertSame(self::USED_AT, $this->reloadFirstUsedAt($takeOver));
    }

    private function reloadFirstUsedAt(ImageFile $imageFile): ?string
    {
        /** @var ImageFile $reloaded */
        $reloaded = $this->entityManager->find(ImageFile::class, (string) $imageFile->getId());

        return $reloaded->getFirstUsedAt()?->format('Y-m-d H:i:s');
    }

    private function createTakeOverOf(ImageFile $original): ImageFile
    {
        $takeOver = $this->createImage();
        $takeOver->getAssetAttributes()->setTakenOverFromId((string) $original->getId());
        $this->entityManager->flush();

        return $takeOver;
    }

    private function createImage(): ImageFile
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = $this->assetLicenceManager->create(
            (new AssetLicence())
                ->setExtSystem($extSystem)
                ->setExtId('first-use-test-' . ++$this->extIdSequence)
        );

        return $this->imageManager->create(
            $this->imageFactory->createFromUrl($licence, 'https://example.test/first-use.jpg')
        );
    }
}
