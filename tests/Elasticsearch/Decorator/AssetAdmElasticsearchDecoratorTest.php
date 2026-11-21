<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Elasticsearch\Decorator;

use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
use AnzuSystems\CoreDamBundle\Elasticsearch\Decorator\AssetAdmElasticsearchDecorator;
use AnzuSystems\CoreDamBundle\Elasticsearch\ElasticSearch;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\SingleUseUsage;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Elastic\Elasticsearch\Client;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetAdmElasticsearchDecoratorTest extends CoreDamKernelTestCase
{
    private const string HOLDER_NAME = 'articleKindStandard';
    private const string HOLDER_ID = '123';

    public function testSingleUseMainFilesAreResolvedWithOneCallbackCallPerPage(): void
    {
        $single = $this->findImage(ImageFixtures::IMAGE_ID_1_1);
        $single->getFlags()->setSingleUse(true);
        $taken = $this->findImage(ImageFixtures::IMAGE_ID_2);
        $taken->getFlags()->setSingleUse(true);
        $taken->getAssetAttributes()->setTakenOverFromId(ImageFixtures::IMAGE_ID_3);
        $plain = $this->findImage(ImageFixtures::IMAGE_ID_3);
        $plain->getFlags()->setSingleUse(false);

        $usage = (new SingleUseUsage())->setTotalCount(2)->setOtherHolderCount(1)->setBlocked(true);
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::once())
            ->method('resolveSingleUseUsage')
            ->with(
                self::callback(static function (array $groupIds): bool {
                    sort($groupIds);
                    $expected = [ImageFixtures::IMAGE_ID_1_1, ImageFixtures::IMAGE_ID_3];
                    sort($expected);

                    return $groupIds === $expected;
                }),
                self::HOLDER_NAME,
                self::HOLDER_ID,
            )
            ->willReturn([ImageFixtures::IMAGE_ID_1_1 => $usage])
        ;

        $items = $this->search($callback, [$single->getAsset(), $taken->getAsset(), $plain->getAsset()]);

        self::assertCount(3, $items);
        self::assertSame($usage, $items[ImageFixtures::IMAGE_ID_1_1]->getSingleUseUsage());
        self::assertNull($items[ImageFixtures::IMAGE_ID_2]->getSingleUseUsage());
        self::assertNull($items[ImageFixtures::IMAGE_ID_3]->getSingleUseUsage());
    }

    public function testCallbackIsNotCalledWithoutSingleUseFiles(): void
    {
        $plain = $this->findImage(ImageFixtures::IMAGE_ID_1_1);
        $plain->getFlags()->setSingleUse(false);
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::never())->method('resolveSingleUseUsage');

        $items = $this->search($callback, [$plain->getAsset()]);

        self::assertNull($items[ImageFixtures::IMAGE_ID_1_1]->getSingleUseUsage());
    }

    public function testCallbackIsNotCalledUnlessTheListAsksForUsage(): void
    {
        $single = $this->findImage(ImageFixtures::IMAGE_ID_1_1);
        $single->getFlags()->setSingleUse(true);
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::never())->method('resolveSingleUseUsage');

        $items = $this->search($callback, [$single->getAsset()], singleUseUsage: false);

        self::assertNull($items[ImageFixtures::IMAGE_ID_1_1]->getSingleUseUsage());
    }

    /**
     * The original and its take over copy are one photo: whichever of them the picker shows, the usage is the same.
     */
    public function testOriginalAndItsCopyGetTheSameUsage(): void
    {
        $original = $this->findImage(ImageFixtures::IMAGE_ID_3);
        $original->getFlags()->setSingleUse(true);
        $copy = $this->findImage(ImageFixtures::IMAGE_ID_2);
        $copy->getFlags()->setSingleUse(true);
        $copy->getAssetAttributes()->setTakenOverFromId(ImageFixtures::IMAGE_ID_3);

        $usage = (new SingleUseUsage())->setTotalCount(2)->setOtherHolderCount(1);
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::once())
            ->method('resolveSingleUseUsage')
            ->with([ImageFixtures::IMAGE_ID_3], self::HOLDER_NAME, self::HOLDER_ID)
            ->willReturn([ImageFixtures::IMAGE_ID_3 => $usage])
        ;

        $items = $this->search($callback, [$original->getAsset(), $copy->getAsset()]);

        self::assertSame($usage, $items[ImageFixtures::IMAGE_ID_3]->getSingleUseUsage());
        self::assertSame($usage, $items[ImageFixtures::IMAGE_ID_2]->getSingleUseUsage());
    }

    public function testFailingCallbackLeavesTheListWithoutUsage(): void
    {
        $single = $this->findImage(ImageFixtures::IMAGE_ID_1_1);
        $single->getFlags()->setSingleUse(true);
        $callback = $this->createStub(ExtSystemCallbackInterface::class);
        $callback->method('resolveSingleUseUsage')->willThrowException(new RuntimeException('CMS unreachable'));

        $items = $this->search($callback, [$single->getAsset()]);

        self::assertCount(1, $items);
        self::assertNull($items[ImageFixtures::IMAGE_ID_1_1]->getSingleUseUsage());
    }

    /**
     * @param list<Asset> $assets
     *
     * @return array<string, AssetAdmListDto>
     */
    private function search(ExtSystemCallbackInterface $callback, array $assets, bool $singleUseUsage = true): array
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $mainFileIds = [];
        foreach ($assets as $asset) {
            $this->getService(IndexManager::class)->index($asset);
            $mainFileIds[] = (string) $asset->getMainFile()?->getId();
        }
        $this->getService(Client::class)->indices()->refresh([
            'index' => $this->getService(IndexSettings::class)->getFullIndexNameByEntity($assets[0]),
        ]);
        $callbackFacade = new ExtSystemCallbackFacade(
            new ServiceLocator([$extSystem->getSlug() => static fn (): ExtSystemCallbackInterface => $callback]),
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        );
        $decorator = new AssetAdmElasticsearchDecorator($this->getService(ElasticSearch::class), $callbackFacade);
        $decorator->setValidator($this->getService(Validator::class));

        $searchDto = (new AssetAdmSearchDto())
            ->setMainFileIds($mainFileIds)
            ->setSingleUseUsage($singleUseUsage)
            ->setUsageHolderName(self::HOLDER_NAME)
            ->setUsageHolderId(self::HOLDER_ID);

        $items = [];
        /** @var AssetAdmListDto $item */
        foreach ($decorator->searchInfiniteListByExtSystem($searchDto, $extSystem)->getData() as $item) {
            $items[(string) $item->getMainFile()?->getId()] = $item;
        }

        return $items;
    }

    private function findImage(string $imageId): ImageFile
    {
        /** @var ImageFile $image */
        $image = $this->entityManager->find(ImageFile::class, $imageId);

        return $image;
    }
}
