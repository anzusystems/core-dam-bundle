<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetStatus;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractAssetFileApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AudioUrl;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\ImageUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

final class ImageApiControllerTest extends AbstractAssetFileApiController
{
    private const TEST_DATA_FILENAME = 'metadata_image.jpeg';


    protected ImageUrlFactory $imageUrlFactory;

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
    public function testUpload(): void
    {
        $rotation = 90;
        $client = $this->getApiClient(User::ID_ADMIN);
        $imageUrl = new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $image = $this->uploadAsset(
            $client,
            $imageUrl,
            self::TEST_DATA_FILENAME,
        );
        $imageEntity = $this->entityManager->find(ImageFile::class, $image->getId());
        $filesystem = $this->filesystemProvider->getFilesystemByStorable($imageEntity);
        $originImagePath = $this->nameGenerator->getPath($imageEntity->getAssetAttributes()->getFilePath());

        // Checks origin file and rotated resizes
        $this->assertFileInFilesystemExists($filesystem, $originImagePath->getFullPath());
        foreach ($imageEntity->getResizes() as $resize)
        {
            $this->assertFileInFilesystemExists($filesystem, $resize->getFilePath());
        }
        $this->assertEquals(3, count($filesystem->listContents($originImagePath->getDir())->toArray()));

        $response = $client->patch($imageUrl->getSingleAssetPath($image->getId())."/rotate/{$rotation}");
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $originImageAttrs = clone $imageEntity->getImageAttributes();
        $imageEntity = $this->entityManager->find(ImageFile::class, $image->getId());
        $this->assertEquals($originImageAttrs->getWidth(), $imageEntity->getImageAttributes()->getHeight());
        $this->assertEquals($originImageAttrs->getHeight(), $imageEntity->getImageAttributes()->getWidth());
        $this->assertEquals($originImageAttrs->getRatioWidth(), $imageEntity->getImageAttributes()->getRatioHeight());
        $this->assertEquals($originImageAttrs->getRatioHeight(), $imageEntity->getImageAttributes()->getRatioWidth());
        $this->assertEquals($rotation, $imageEntity->getImageAttributes()->getRotation());

        foreach ($imageEntity->getResizes() as $resize)
        {
            $this->assertFileInFilesystemExists($filesystem, $resize->getFilePath());
        }
        $this->assertEquals(3, count($filesystem->listContents($originImagePath->getDir())->toArray()));

        // get image url to create crop cache and validate.
        $response = $client->get(
            'http://image.anzusystems.localhost' . $this->imageUrlFactory->generatePublicUrl($image->getId(), 800, 450, 0)
        );
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $cropFilesystem = $this->filesystemProvider->getCropFilesystemByExtSystemSlug($imageEntity->getExtSystem()->getSlug());
        $this->assertEquals(1, count($cropFilesystem->listContents($originImagePath->getDir())->toArray()));

        $this->delete(
            $client,
            $imageUrl,
            $image->getId(),
            Response::HTTP_NO_CONTENT
        );
        $this->assertEquals(0, count($filesystem->listContents($originImagePath->getDir())->toArray()));
        $this->assertEquals(0, count($cropFilesystem->listContents($originImagePath->getDir())->toArray()));
    }

    public function testCreateToAsset(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $response = $client->post(AssetUrl::createPath(), ['type' => 'image']);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $asset = $this->serializer->deserialize($response->getContent(), AssetAdmDetailDto::class);

        $response = $this->addToSlot(
            apiClient: $client,
            assetUrl: new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID),
            file: $this->getFile(self::TEST_DATA_FILENAME),
            assetId: $asset->getId(),
            position: 'default',
            expectedStatusCode: Response::HTTP_CREATED
        );
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->serializer->deserialize($response->getContent(), ImageFileAdmDetailDto::class);
    }

    public function testSetSlotSuccess(): void
    {
        $this->testSlotsSuccess(
            $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1),
            $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_2),
            'extra',
            new ImageUrl(1)
        );
    }

    /**
     * @dataProvider createToAssetFailedDataProvider
     */
    public function testCreateToAssetFailed(string $imageId, string $slot, string $error): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $response = $client->post(AssetUrl::createPath(), ['type' => 'image']);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $asset = $this->serializer->deserialize($response->getContent(), AssetAdmDetailDto::class);

        $response = $client->patch(
            (new ImageUrl(1))
                ->setToSlot($asset->getId(), $imageId, $slot),
            ['type' => 'image']
        );
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertForbiddenOperationError($response->getContent(), $error);
    }

    public function createToAssetFailedDataProvider(): array
    {
        return [
            [
                ImageFixtures::IMAGE_ID_2,
                'new',
                ForbiddenOperationException::DETAIL_INVALID_ASSET_SLOT
            ]
        ];
    }

    public function testCreateImageFailed(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $responseData = $this->createAsset(
            $client,
            (new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID)),
            $this->getFile(self::TEST_DATA_FILENAME),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'mimeType' => 'video/mp4',
                'size' => 0
            ]
        )->getContent();

        $this->assertEquals(
            [
                'mimeType' => [
                    'error_field_invalid'
                ],
                'size' => [
                    'error_field_length_min'
                ]
            ],
            json_decode($responseData, true)['fields'],
        );
    }

    /**
     * @dataProvider addChunkFailedDataProvider
     */
    public function testAddChunkFailed(array $reqBody, array $validationFieldsBody): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $responseData = $this->uploadChunk(
            $client,
            (new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID)),
            $this->getFile(self::TEST_DATA_FILENAME),
            ImageFixtures::IMAGE_UPLOADING_ID_4,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $reqBody
        )->getContent();

        $this->assertEquals(
            $validationFieldsBody,
            json_decode($responseData, true)['fields'],
        );
    }

    public function addChunkFailedDataProvider(): array
    {
        return [
            [
                [
                    'offset' => 10,
                    'size' => 100,
                ],
                [
                    'offset' => [
                        'error_field_invalid'
                    ],
                    'size' => [
                        'error_field_invalid',
                        'error_field_length_min'
                    ]
                ]
            ],
            [
                [
                    'size' => 500000,
                ],
                [
                    'size' => [
                        'error_field_invalid',
                        'error_field_length_max'
                    ]
                ]
            ]
        ];
    }


    public function testFinishUploadFailed(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $responseData = $this->finishUpload(
            $client,
            (new ImageUrl(AssetLicenceFixtures::DEFAULT_LICENCE_ID)),
            $this->getFile(self::TEST_DATA_FILENAME),
            ImageFixtures::IMAGE_UPLOADING_ID_4,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        )->getContent();

        $this->assertEquals(
          'asset_not_fully_uploaded',
            json_decode($responseData, true)['detail']
        );
    }
}
