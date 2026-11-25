<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Elastic\Elasticsearch\Client;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\Response;

final class AssetSearchSourcesTest extends AbstractApiController
{
    use AuthorTreeTrait;

    public function testFiltersAndSearchesSources(): void
    {
        $source = $this->createSource('Zebrafoto Agency');
        $asset = $this->createIndexedImageWithSource($source);
        $assetId = (string) $asset->getId();

        try {
            $client = $this->getApiClient(User::ID_ADMIN);
            $url = AssetUrl::licenceSearch([$asset->getLicence()->getId()]);

            $response = $client->get($url . '&sourceIds=' . $source->getId());
            self::assertStatusCode($response, Response::HTTP_OK);
            self::assertSame([(string) $asset->getId()], $this->getIds($response));

            $response = $client->get($url . '&sourceIds=00000000-0000-4000-8000-000000000000');
            self::assertStatusCode($response, Response::HTTP_OK);
            self::assertSame([], $this->getIds($response));

            $response = $client->get($url . '&text=Zebrafoto');
            self::assertStatusCode($response, Response::HTTP_OK);
            self::assertContains((string) $asset->getId(), $this->getIds($response));
        } finally {
            $this->reindexWithoutSources($assetId);
        }
    }

    public function testAuthorIdsFilterFindsAuthorsAndSources(): void
    {
        $source = $this->createSource('Zebrafoto Video Agency');
        $sourceId = (string) $source->getId();
        $imageAsset = $this->createIndexedImageWithSource($source);
        $imageAssetId = (string) $imageAsset->getId();
        $videoAssetId = $this->createIndexedVideoWithAuthor($source);

        try {
            $response = $this->getApiClient(User::ID_ADMIN)->get(
                AssetUrl::licenceSearch([$imageAsset->getLicence()->getId()]) . '&authorIds=' . $sourceId
            );
            self::assertStatusCode($response, Response::HTTP_OK);
            self::assertEqualsCanonicalizing([$imageAssetId, $videoAssetId], $this->getIds($response));
        } finally {
            $this->reindexWithoutSources($imageAssetId);
            $this->reindexVideoWithoutAuthor($videoAssetId, $sourceId);
        }
    }

    public function testListReturnsSourcesInOneQuery(): void
    {
        $source = $this->createSource('Zebrafoto List Agency');
        $sourceId = (string) $source->getId();
        $firstAsset = $this->createIndexedImageWithSource($source);
        $secondAsset = $this->createIndexedImageWithSource($source, ImageFixtures::IMAGE_ID_1_2);
        $assetIds = [(string) $firstAsset->getId(), (string) $secondAsset->getId()];
        $url = AssetUrl::licenceSearch([$firstAsset->getLicence()->getId()]);
        $this->entityManager->clear();
        $client = $this->getApiClient(User::ID_ADMIN);
        /** @var DebugDataHolder $debugDataHolder */
        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();

        try {
            $response = $client->get($url);
            $queries = array_column($debugDataHolder->getData()['default'] ?? [], 'sql');
            self::assertStatusCode($response, Response::HTTP_OK);

            $sources = array_column(json_decode((string) $response->getContent(), true)['data'], 'sources', 'id');
            foreach ($assetIds as $assetId) {
                self::assertSame([$sourceId], $sources[$assetId]);
            }
            self::assertContains([], $sources);
            self::assertCount(1, array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'asset_source')));
        } finally {
            foreach ($assetIds as $assetId) {
                $this->reindexWithoutSources($assetId);
            }
        }
    }

    private function createIndexedVideoWithAuthor(Author $author): string
    {
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $video);
        $asset = $video->getAsset();
        $asset->addAuthor($author);
        $this->entityManager->flush();
        $this->indexAsset($asset);

        return (string) $asset->getId();
    }

    private function reindexVideoWithoutAuthor(string $assetId, string $authorId): void
    {
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, $assetId);
        self::assertInstanceOf(Asset::class, $asset);
        $asset->getAuthors()->removeElement($this->entityManager->find(Author::class, $authorId));
        $this->entityManager->flush();
        $this->indexAsset($asset);
    }

    private function createIndexedImageWithSource(Author $source, string $imageId = ImageFixtures::IMAGE_ID_1_1): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, $imageId);
        self::assertInstanceOf(ImageFile::class, $image);
        $asset = $image->getAsset();
        $asset->addSource($source);
        $this->entityManager->flush();
        $this->indexAsset($asset);

        return $asset;
    }

    private function reindexWithoutSources(string $assetId): void
    {
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, $assetId);
        self::assertInstanceOf(Asset::class, $asset);
        $asset->getSources()->clear();
        $this->indexAsset($asset);
    }

    private function indexAsset(Asset $asset): void
    {
        $this->getService(IndexManager::class)->index($asset);
        $this->getService(Client::class)->indices()->refresh([
            'index' => $this->getService(IndexSettings::class)->getFullIndexNameByEntity($asset),
        ]);
    }

    /**
     * @return list<string>
     */
    private function getIds(Response $response): array
    {
        return array_column(json_decode((string) $response->getContent(), true)['data'], 'id');
    }
}
