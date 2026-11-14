<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Author;

use AnzuSystems\CoreDamBundle\Domain\Author\AuthorProvider;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;

final class AuthorSourceRoutingTest extends CoreDamKernelTestCase
{
    use AuthorTreeTrait;

    private AuthorProvider $authorProvider;
    private ExtSystem $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorProvider = $this->getService(AuthorProvider::class);
        $cms = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);
        self::assertInstanceOf(ExtSystem::class, $cms);
        $this->cms = $cms;
    }

    public function testRoutesCurrentAuthorsByFlag(): void
    {
        $tree = $this->createAuthorTree($this->cms, [
            'tasr' => [
                'name' => 'TASR',
                'reviewed' => true,
                'source' => true,
            ],
            'sme' => [
                'name' => 'SME',
                'reviewed' => true,
                'source' => true,
            ],
            'photographer' => [
                'name' => 'Ján Krošlák',
            ],
            'tasrChild' => [
                'name' => 'TASR/Ján Krošlák',
                'parents' => ['tasr', 'photographer'],
            ],
        ]);
        $image = $this->createAsset(AssetType::Image);
        $image->addAuthor($tree['tasrChild'])->addAuthor($tree['sme']);
        $image->addSource($tree['photographer']);

        self::assertTrue($this->authorProvider->provideCurrentAuthorToColl($image));
        self::assertSame(['photographer'], $this->toTreeKeys($tree, $image->getAuthors()));
        self::assertSame(['sme', 'tasr'], $this->toTreeKeys($tree, $image->getSources()));
        self::assertFalse($this->authorProvider->provideCurrentAuthorToColl($image));

        $video = $this->createAsset(AssetType::Video);
        $video->addAuthor($tree['tasrChild'])->addAuthor($tree['sme']);

        self::assertTrue($this->authorProvider->provideCurrentAuthorToColl($video));
        self::assertSame(['photographer', 'sme', 'tasr'], $this->toTreeKeys($tree, $video->getAuthors()));
        self::assertSame([], $this->toTreeKeys($tree, $video->getSources()));
    }

    private function createAsset(AssetType $type): Asset
    {
        $asset = (new Asset())->setExtSystem($this->cms);
        $asset->getAttributes()->setAssetType($type);

        return $asset;
    }
}
