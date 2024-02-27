<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceGroupRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures as TestAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetLicenceGroupUrl;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetLicenceUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AssetLicenceGroupControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceGroupUrl::getOne(AssetLicenceGroupFixtures::LICENCE_GROUP_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $fromDb = self::getContainer()
            ->get(AssetLicenceGroupRepository::class)
            ->find(AssetLicenceGroupFixtures::LICENCE_GROUP_ID);

        $this->assertSame($fromDb->getId(), $assetLicence->getId());
        $this->assertSame($fromDb->getName(), $assetLicence->getName());
        $this->assertSame($fromDb->getExtSystem()->getId(), $assetLicence->getExtSystem()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceGroupUrl::getList());
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
     * @param array{name: string, extSystem: int, licences: int[]} $requestJson
     *
     * @throws SerializerException
     */
    public function testCreateSuccess(array $requestJson, int $expectedResponseStatusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceGroupUrl::createPath(), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicenceGroup = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $this->assertSame($requestJson['name'], $assetLicenceGroup->getName());
        $this->assertSame($requestJson['extSystem'], $assetLicenceGroup->getExtSystem()->getId());
        $this->assertSame($requestJson['licences'], $assetLicenceGroup->getLicences()->map(
            fn (AssetLicence $licence): int => (int) $licence->getId()
        )->toArray());
    }

    /**
     * @return list<array{requestJson: array{name: string, extSystem: int, licences: int[]}, expectedResponseStatusCode: int}>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'name' => 'test',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'licences' => [TestAssetLicenceFixtures::LICENCE_ID],
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

        $response = $client->post(AssetLicenceGroupUrl::createPath(), $requestJson);
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
                ],
            ],
            [
                'requestJson' => [
                    'name' => 'Group 100',
                    'extSystem' => 4,
                ],
                'validationErrors' => [
                    'name' => [
                        ValidationException::ERROR_FIELD_UNIQUE,
                    ]
                ],
            ],
            [
                'requestJson' => [
                    'name' => 'Group',
                    'extSystem' => 4,
                    'licences' => [AssetLicenceFixtures::DEFAULT_LICENCE_ID]
                ],
                'validationErrors' => [
                    'licences' => [
                        ValidationException::ERROR_FIELD_INVALID,
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
        $response = $client->put(AssetLicenceGroupUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicenceGroup = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $this->assertSame($requestJson['name'], $assetLicenceGroup->getName());
        $this->assertSame($requestJson['extSystem'], $assetLicenceGroup->getExtSystem()->getId());
        $this->assertSame($requestJson['licences'], $assetLicenceGroup->getLicences()->map(
            fn (AssetLicence $licence): int => (int) $licence->getId()
        )->toArray());
    }

    public function updateSuccessDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'id' => AssetLicenceGroupFixtures::LICENCE_GROUP_ID,
                    'name' => 'test (updated)',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'licences' => [TestAssetLicenceFixtures::LICENCE_ID, TestAssetLicenceFixtures::LICENCE_2_ID],
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }
}
