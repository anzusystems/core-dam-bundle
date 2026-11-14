<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use Elastic\Elasticsearch\Client;
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

    private function createIndexedImageWithSource(Author $source): Asset
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
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
