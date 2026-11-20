<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorDefaultSourceFacade;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DBALRepository\AssetSourceDBALRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;

final class AuthorDefaultSourceFacadeTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private AuthorDefaultSourceFacade $authorDefaultSourceFacade;
    private AssetSourceDBALRepository $assetSourceDBALRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorDefaultSourceFacade = $this->getService(AuthorDefaultSourceFacade::class);
        $this->authorDefaultSourceFacade->setBatchSize(2);
        $this->assetSourceDBALRepository = $this->getService(AssetSourceDBALRepository::class);
    }

    public function testFillsImagesWithFileWithoutSourceInBatches(): void
    {
        $this->markDefaultSource();
        $tasr = $this->createSource('TASR');
        $this->findImageAsset(ImageFixtures::IMAGE_ID_2)->addSource($tasr);
        $this->findImageAsset(ImageFixtures::IMAGE_ID_3)->getAttributes()->setStatus(AssetStatus::Draft);
        $this->entityManager->flush();
        $withFile = $this->countWithoutSource(AssetStatus::WithFile);
        $drafts = $this->countWithoutSource(AssetStatus::Draft);
        self::assertGreaterThan(2, $withFile);

        $batches = $this->fill();

        self::assertSame($withFile, array_sum($batches));
        self::assertCount((int) ceil(($withFile + 1) / 2), $batches);
        self::assertSame(0, $this->countWithoutSource(AssetStatus::WithFile));
        self::assertSame($drafts, $this->countWithoutSource(AssetStatus::Draft));
        self::assertSame([AuthorFixtures::AUTHOR_5], $this->sourceIds(ImageFixtures::IMAGE_ID_1_1));
        self::assertSame([(string) $tasr->getId()], $this->sourceIds(ImageFixtures::IMAGE_ID_2));
        self::assertSame([], $this->sourceIds(ImageFixtures::IMAGE_ID_3));

        self::assertSame([0], $this->fill());
    }

    /**
     * @return list<int>
     */
    private function fill(): array
    {
        $this->entityManager->clear();
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $extSystem);
        $source = $this->getService(AuthorProvider::class)->getDefaultSource($extSystem, AssetType::Image);
        self::assertInstanceOf(Author::class, $source);

        return iterator_to_array($this->authorDefaultSourceFacade->fillDefaultSource($extSystem, $source), preserve_keys: false);
    }

    private function countWithoutSource(AssetStatus $status): int
    {
        return $this->assetSourceDBALRepository->countImagesWithoutSource(ExtSystemFixtures::ID_CMS)[$status->value] ?? 0;
    }

    private function findImageAsset(string $imageId): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        self::assertInstanceOf(ImageFile::class, $image);

        return $image->getAsset();
    }

    /**
     * @return list<string>
     */
    private function sourceIds(string $imageId): array
    {
        $this->entityManager->clear();

        return $this->findImageAsset($imageId)->getSources()
            ->map(static fn (Author $source): string => (string) $source->getId())
            ->getValues();
    }
}
