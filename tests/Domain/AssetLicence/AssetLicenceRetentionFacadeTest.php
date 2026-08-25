<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetLicence;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceRetentionFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Event\AssetDeleteEvent;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Ext system checkImageUsedOnDelete defaults to false and no ExtSystemCallbackInterface is registered
 * for the fixture ext systems, so enabling the flag exercises the real fail-closed used path
 * (see AssetFacadeTest) without needing to mock the callback boundary.
 */
final class AssetLicenceRetentionFacadeTest extends CoreDamKernelTestCase
{
    private AssetLicenceRetentionFacade $retentionFacade;
    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;
    private int $extIdSequence = App::ZERO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retentionFacade = $this->getService(AssetLicenceRetentionFacade::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
    }

    public function testDeleteExpiredAssetsRemovesOnlyAssetsOlderThanThreshold(): void
    {
        $licence = $this->createRetentionLicence(active: true, olderThanDays: 5);
        $oldAsset = $this->createImageAsset($licence, App::getAppDate()->modify('-10 days'));
        $youngAsset = $this->createImageAsset($licence, App::getAppDate()->modify('-2 days'));
        $this->entityManager->flush();
        $oldAssetId = (string) $oldAsset->getId();
        $youngAssetId = (string) $youngAsset->getId();

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(1, $deleted);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Asset::class, $oldAssetId));
        self::assertNotNull($this->entityManager->find(Asset::class, $youngAssetId));
    }

    public function testDeleteExpiredAssetsSkipsLicenceWithInactiveAutoDelete(): void
    {
        $licence = $this->createRetentionLicence(active: false, olderThanDays: 5);
        $oldAsset = $this->createImageAsset($licence, App::getAppDate()->modify('-30 days'));
        $this->entityManager->flush();
        $oldAssetId = (string) $oldAsset->getId();

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(0, $deleted);
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Asset::class, $oldAssetId));
    }

    /**
     * AssetLicenceFacade::update rejects active=true + olderThanDays<=1, but that validation lives above
     * this facade — a fixture can still write it straight to the entity, so retention needs its own guard
     * (outer loop skip + `olderThanDays > 1` in the DQL) rather than trusting the upstream validator.
     */
    public function testDeleteExpiredAssetsSkipsLicenceWithOlderThanDaysAtOne(): void
    {
        $licence = $this->createRetentionLicence(active: true, olderThanDays: 1);
        $oldAsset = $this->createImageAsset($licence, App::getAppDate()->modify('-30 days'));
        $this->entityManager->flush();
        $oldAssetId = (string) $oldAsset->getId();

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(0, $deleted);
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Asset::class, $oldAssetId));
    }

    public function testDeleteExpiredAssetsSkipsUsedAssetsAndCompletesTheRun(): void
    {
        $licence = $this->createRetentionLicence(active: true, olderThanDays: 5);
        $licence->getExtSystem()->getFlags()->setCheckImageUsedOnDelete(true);
        $usedAssetOne = $this->createImageAsset($licence, App::getAppDate()->modify('-10 days'));
        $usedAssetTwo = $this->createImageAsset($licence, App::getAppDate()->modify('-15 days'));
        $this->entityManager->flush();
        $usedAssetOneId = (string) $usedAssetOne->getId();
        $usedAssetTwoId = (string) $usedAssetTwo->getId();

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(0, $deleted);
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Asset::class, $usedAssetOneId));
        self::assertNotNull($this->entityManager->find(Asset::class, $usedAssetTwoId));
    }

    public function testDeleteExpiredAssetsContinuesAcrossMultipleBatches(): void
    {
        $this->retentionFacade->setBulkSize(2);
        $licence = $this->createRetentionLicence(active: true, olderThanDays: 5);
        $assets = [
            $this->createImageAsset($licence, App::getAppDate()->modify('-10 days')),
            $this->createImageAsset($licence, App::getAppDate()->modify('-11 days')),
            $this->createImageAsset($licence, App::getAppDate()->modify('-12 days')),
        ];
        $this->entityManager->flush();
        $assetIds = array_map(static fn (Asset $asset): string => (string) $asset->getId(), $assets);

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(3, $deleted);
        $this->entityManager->clear();
        foreach ($assetIds as $assetId) {
            self::assertNull($this->entityManager->find(Asset::class, $assetId));
        }
    }

    /**
     * Hook: AssetDeleteEvent fires once per deleted asset, right after the batch's commit and before
     * the facade's entityManager->clear() + per-batch re-find — precisely the "between batches" window.
     * A callback-based hook (ExtSystemCallbackInterface, as used in ExtSystemCallbackFacadeTest) was
     * considered per the task brief, but no implementation of that interface is registered in this
     * bundle's test container (see the class docblock above and AssetFacadeTest), so its compiled
     * ServiceLocator is empty and cannot intercept anything here without production code changes.
     */
    public function testDeleteExpiredAssetsStopsWhenLicenceIsDisabledMidRun(): void
    {
        $this->retentionFacade->setBulkSize(1);
        $licence = $this->createRetentionLicence(active: true, olderThanDays: 5);
        $licenceId = $licence->getId();
        $assets = [
            $this->createImageAsset($licence, App::getAppDate()->modify('-10 days')),
            $this->createImageAsset($licence, App::getAppDate()->modify('-11 days')),
            $this->createImageAsset($licence, App::getAppDate()->modify('-12 days')),
        ];
        $this->entityManager->flush();
        $assetIds = array_map(static fn (Asset $asset): string => (string) $asset->getId(), $assets);
        $connection = $this->entityManager->getConnection();
        $disabled = false;

        $this->getService(EventDispatcherInterface::class)->addListener(
            AssetDeleteEvent::class,
            static function () use (&$disabled, $connection, $licenceId): void {
                if ($disabled) {
                    return;
                }
                $disabled = true;

                // Simulates an admin flipping the flag mid-sweep via a raw write that bypasses the EM
                // identity map — exactly what the per-batch re-find after entityManager->clear() must see.
                $connection->executeStatement(
                    'UPDATE asset_licence SET auto_delete_active = 0 WHERE id = ?',
                    [$licenceId]
                );
            }
        );

        $deleted = $this->retentionFacade->deleteExpiredAssets();

        self::assertSame(1, $deleted);
        $this->entityManager->clear();
        $remaining = App::ZERO;
        foreach ($assetIds as $assetId) {
            if (null !== $this->entityManager->find(Asset::class, $assetId)) {
                ++$remaining;
            }
        }
        self::assertSame(2, $remaining);
    }

    private function createRetentionLicence(bool $active, int $olderThanDays): AssetLicence
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = (new AssetLicence())
            ->setExtSystem($extSystem)
            ->setExtId('retention-test-' . ++$this->extIdSequence);
        $licence->getAutoDelete()
            ->setActive($active)
            ->setOlderThanDays($olderThanDays);

        return $this->assetLicenceManager->create($licence);
    }

    private function createImageAsset(AssetLicence $licence, DateTimeImmutable $createdAt): Asset
    {
        $imageFile = $this->imageFactory->createFromUrl($licence, 'https://example.test/retention.jpg');
        $asset = $this->assetFactory->createForAssetFile($imageFile, $licence);
        $this->imageManager->create($imageFile, flush: false);
        $asset->setCreatedAt($createdAt);

        return $asset;
    }
}
