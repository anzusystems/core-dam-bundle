<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\DistributionCategoryFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\ImageUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

final class AssetApiControllerTest extends AbstractAssetFileApiController
{
    private const TEST_DATA_FILENAME = 'metadata_image.jpeg';
    private const TEST_DATA_2_FILENAME = 'solid_image.jpeg';

    protected ImageUrlFactory $imageUrlFactory;

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdate(int $statusCode, ?string $categoryId = null): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);

        $response = $client->put(
            '/api/adm/v1/asset/'. $video->getAsset()->getId(),
            [
                'id' => $video->getAsset()->getId(),
                'distributionCategory' => $categoryId
            ]
        );

        $this->assertEquals($statusCode, $response->getStatusCode());
    }

    private function updateDataProvider(): array
    {
        return [
            [
                Response::HTTP_OK,
                DistributionCategoryFixtures::CATEGORY_2,
            ],
            [
                Response::HTTP_OK,
                null,
            ],
            [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                DistributionCategoryFixtures::CATEGORY_1,
            ],
            [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                DistributionCategoryFixtures::CATEGORY_4,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        /** @var ImageUrlFactory $imageUrlFactory */
        $imageUrlFactory = static::getContainer()->get(ImageUrlFactory::class);
        $this->imageUrlFactory = $imageUrlFactory;
    }

    /**
     * @throws SerializerException|FilesystemException
     */
    public function testDelete(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $imageUrl = new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $image = $this->uploadAsset(
            $client,
            $imageUrl,
            self::TEST_DATA_FILENAME,
        );
        $imageEntity = $this->entityManager->find(ImageFile::class, $image->getId());
        $filesystem = $this->filesystemProvider->getFilesystemByStorable($imageEntity);
        $cropFilesystem = $this->filesystemProvider->getCropFilesystemByExtSystemSlug($imageEntity->getExtSystem()->getSlug());
        $originImagePath = $this->nameGenerator->getPath($imageEntity->getAssetAttributes()->getFilePath());

        $secondFile = $this->getFile(self::TEST_DATA_2_FILENAME);
        $assetId = $imageEntity->getAsset()->getId();

        $this->addToSlot($client, $imageUrl, $secondFile, $assetId, 'default', Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->addToSlot($client, $imageUrl, $secondFile, $assetId, 'undefined', Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->addToSlot($client, $imageUrl, $secondFile, $assetId, 'free', Response::HTTP_CREATED);

        $this->entityManager->getRepository(ImageFile::class)->findBy([
            'asset' => $assetId
        ]);
        $this->entityManager->find(Asset::class, $assetId);

        $response = $client->get(
            'http://image.anzusystems.localhost' . $this->imageUrlFactory->generatePublicUrl($image->getId(), 800, 450, 0)
        );
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $response = $client->delete('/api/adm/v1/asset/'. $imageEntity->getAsset()->getId());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertEquals(0, count($filesystem->listContents($originImagePath->getDir())->toArray()));
        $this->assertEquals(0, count($cropFilesystem->listContents($originImagePath->getDir())->toArray()));
    }

    /**
     * @dataProvider getData
     */
    public function testCreate(string $type): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $response = $client->post('/api/adm/v1/asset/licence/'.AssetLicenceFixtures::DEFAULT_LICENCE_ID, ['type' => $type]);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    private function getData(): array
    {
        return [
            ['image'],
        ];
    }
}
