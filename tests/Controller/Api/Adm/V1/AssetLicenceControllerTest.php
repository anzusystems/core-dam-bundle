<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetLicenceUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AssetLicenceControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceUrl::getOne(AssetLicenceFixtures::DEFAULT_LICENCE_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicence::class
        );

        $fromDb = self::getContainer()
            ->get(AssetLicenceRepository::class)
            ->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $this->assertSame($fromDb->getId(), $assetLicence->getId());
        $this->assertSame($fromDb->getName(), $assetLicence->getName());
        $this->assertSame($fromDb->getExtSystem()->getId(), $assetLicence->getExtSystem()->getId());
        $this->assertSame($fromDb->getExtId(), $assetLicence->getExtId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceUrl::getList());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($assetLicence->getData()));
    }


    /**
     * @dataProvider createSuccessDataProvider
     *
     * @param array{name: string, extSystem: int, extId: string} $requestJson
     *
     * @throws SerializerException
     */
    public function testCreateSuccess(array $requestJson, int $expectedResponseStatusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceUrl::createPath(), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicence::class
        );

        $this->assertSame($requestJson['name'], $assetLicence->getName());
        $this->assertSame($requestJson['extId'], $assetLicence->getExtId());
        $this->assertSame($requestJson['extSystem'], $assetLicence->getExtSystem()->getId());
    }

    /**
     * @return list<array{requestJson: array{name: string, extSystem: int, extId: string}, expectedResponseStatusCode: int}>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'name' => 'test',
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'extId' => (string) Uuid::v7(),
                ],
                'expectedResponseStatusCode' => Response::HTTP_CREATED,
            ],
        ];
    }

    /**
     * @dataProvider createFailureDataProvider
     */
    public function testCreateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceUrl::createPath(), $requestJson);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    public function createFailureDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'name' => 'a',
                    'extSystem' => 0,
                ],
                'validationErrors' => [
                    'name' => [
                        ValidationException::ERROR_FIELD_LENGTH_MIN,
                    ],
                    'extSystem' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ],
                    'extId' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ]
                ],
            ],
            [
                'requestJson' => [
                    'name' => 'duplicate',
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'extId' => '1',
                ],
                'validationErrors' => [
                    'extId' => [
                        ValidationException::ERROR_FIELD_UNIQUE,
                    ]
                ],
            ],
        ];
    }

    /**
     * @dataProvider updateSuccessDataProvider
     *
     * @throws SerializerException
     */
    public function testUpdateSuccess(array $requestJson, int $expectedResponseStatusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $id = $requestJson['id'];
        $response = $client->put(AssetLicenceUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicence::class
        );

        $this->assertSame($requestJson['id'], $assetLicence->getId());
        $this->assertSame($requestJson['name'], $assetLicence->getName());
        $this->assertSame($requestJson['extId'], $assetLicence->getExtId());
    }

    public function updateSuccessDataProvider(): array
    {
        $existingAssetLicence = self::getContainer()
            ->get(AssetLicenceRepository::class)
            ->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        return [
            [
                'requestJson' => [
                    'id' => $existingAssetLicence->getId(),
                    'name' => 'test (updated)',
                    'extSystem' => $existingAssetLicence->getExtSystem()->getId(),
                    'extId' => 'updated',
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }
}
