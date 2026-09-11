<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;

final class AuthorProviderTest extends CoreDamKernelTestCase
{
    private AuthorProvider $authorProvider;
    private AssetLicenceManager $assetLicenceManager;
    private AssetFactory $assetFactory;
    private ImageFactory $imageFactory;
    private ImageManager $imageManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorProvider = $this->getService(AuthorProvider::class);
        $this->assetLicenceManager = $this->getService(AssetLicenceManager::class);
        $this->assetFactory = $this->getService(AssetFactory::class);
        $this->imageFactory = $this->getService(ImageFactory::class);
        $this->imageManager = $this->getService(ImageManager::class);
    }

    /**
     * AuthorFixtures::AUTHOR_4 is an unreviewed alias of AUTHOR_5 and AUTHOR_6.
     */
    public function testProvideAuthorToCollAddsAuthorAndResolvesAliasWithoutDuplicates(): void
    {
        $asset = $this->createAsset();
        $asset->addAuthor($this->author(AuthorFixtures::AUTHOR_4));

        self::assertTrue($this->authorProvider->provideAuthorToColl($asset, $this->author(AuthorFixtures::AUTHOR_5)));
        self::assertSame($this->sorted([AuthorFixtures::AUTHOR_5, AuthorFixtures::AUTHOR_6]), $this->authorIds($asset));

        self::assertFalse($this->authorProvider->provideAuthorToColl($asset, $this->author(AuthorFixtures::AUTHOR_5)));
        self::assertSame($this->sorted([AuthorFixtures::AUTHOR_5, AuthorFixtures::AUTHOR_6]), $this->authorIds($asset));
    }

    private function createAsset(): Asset
    {
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        $licence = $this->assetLicenceManager->create(
            (new AssetLicence())
                ->setExtSystem($extSystem)
                ->setExtId('author-provider-test')
        );
        $imageFile = $this->imageFactory->createFromUrl($licence, 'https://example.test/author-provider.jpg');
        $asset = $this->assetFactory->createForAssetFile($imageFile, $licence);
        $this->imageManager->create($imageFile);

        return $asset;
    }

    private function author(string $id): Author
    {
        /** @var Author $author */
        $author = $this->entityManager->find(Author::class, $id);

        return $author;
    }

    /**
     * @return list<string>
     */
    private function authorIds(Asset $asset): array
    {
        return $this->sorted(
            $asset->getAuthors()
                ->map(static fn (Author $author): string => (string) $author->getId())
                ->getValues()
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
