<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Asset;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ImageFixtures;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The bundle registers no ExtSystemCallbackInterface implementation for the fixture
 * ext system, so with checkImageUsedOnDelete enabled these exercise the real
 * fail-closed path (missing callback => treated as used => delete blocked).
 */
final class AssetFacadeTest extends CoreDamKernelTestCase
{
    private AssetFacade $assetFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetFacade = $this->getService(AssetFacade::class);
    }

    #[DataProvider('deleteOperationDataProvider')]
    public function testDeleteIsBlockedWhenImageUsageCannotBeVerified(bool $bulk): void
    {
        $asset = $this->getUsageCheckedFixtureAsset();

        try {
            $bulk
                ? $this->assetFacade->deleteBulk(new ArrayCollection([$asset]))
                : $this->assetFacade->delete($asset);
            self::fail('Expected ForbiddenOperationException was not thrown.');
        } catch (ForbiddenOperationException $exception) {
            self::assertSame(ForbiddenOperationException::FILE_IS_USED, $exception->getDetail());
        }
    }

    public static function deleteOperationDataProvider(): array
    {
        return [
            'delete' => ['bulk' => false],
            'deleteBulk' => ['bulk' => true],
        ];
    }

    public function testDeleteUnfinishedUploadsSkipsAssetsWhoseUsageCannotBeVerified(): void
    {
        $usedAsset = $this->createUnfinishedDraftAsset(AssetLicenceFixtures::LICENCE_ID, checkUsedOnDelete: true);
        $unusedAssetOne = $this->createUnfinishedDraftAsset(AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE, checkUsedOnDelete: false);
        $unusedAssetTwo = $this->createUnfinishedDraftAsset(AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE, checkUsedOnDelete: false);
        $this->entityManager->flush();

        $usedAssetId = (string) $usedAsset->getId();
        $unusedAssetOneId = (string) $unusedAssetOne->getId();
        $unusedAssetTwoId = (string) $unusedAssetTwo->getId();

        $transitioned = $this->assetFacade->deleteUnfinishedUploads();

        self::assertSame(2, $transitioned);

        // The asset change-state message is consumed synchronously in tests (`sync://` transport), so a
        // transitioned asset is fully deleted by the time deleteUnfinishedUploads() returns, not merely
        // left in the Deleting status.
        $this->entityManager->clear();
        self::assertSame(AssetStatus::Draft, $this->findAssetStatus($usedAssetId));
        self::assertNull($this->entityManager->find(Asset::class, $unusedAssetOneId));
        self::assertNull($this->entityManager->find(Asset::class, $unusedAssetTwoId));
    }

    private function getUsageCheckedFixtureAsset(): Asset
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1);
        $image->getExtSystem()->getFlags()->setCheckImageUsedOnDelete(true);

        return $image->getAsset();
    }

    private function createUnfinishedDraftAsset(int $licenceId, bool $checkUsedOnDelete): Asset
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, $licenceId);
        $licence->getExtSystem()->getFlags()->setCheckImageUsedOnDelete($checkUsedOnDelete);

        $imageFile = $this->getService(ImageFactory::class)->createFromUrl($licence, 'https://example.test/unfinished.jpg');
        $asset = $this->getService(AssetFactory::class)->createForAssetFile($imageFile, $licence);
        $this->getService(ImageManager::class)->create($imageFile, flush: false);
        $asset->setCreatedAt(App::getAppDate()->modify('-8 days'));

        return $asset;
    }

    private function findAssetStatus(string $assetId): AssetStatus
    {
        /** @var Asset $asset */
        $asset = $this->entityManager->find(Asset::class, $assetId);

        return $asset->getAttributes()->getStatus();
    }
}
