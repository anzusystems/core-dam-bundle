<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetLicenceUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @param array{name: string, extSystem: int, extId: string} $requestJson
     *
     * @throws SerializerException
     */
    #[DataProvider('createSuccessDataProvider')]
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
    public static function createSuccessDataProvider(): array
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

    #[DataProvider('createFailureDataProvider')]
    public function testCreateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceUrl::createPath(), $requestJson);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    public static function createFailureDataProvider(): array
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
     * @throws SerializerException
     */
    #[DataProvider('updateSuccessDataProvider')]
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

    public static function updateSuccessDataProvider(): array
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

    /**
     * @throws SerializerException
     */
    public function testCreateWithInternalRuleAndAuthors(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceUrl::createPath(), [
            'name' => 'licence-with-internal-rule',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'extId' => (string) Uuid::v7(),
            'internalRule' => [
                'active' => true,
                'markAsInternalSince' => '2025-01-01T00:00:00.000000Z',
            ],
            'internalRuleAuthors' => [AuthorFixtures::AUTHOR_1, AuthorFixtures::AUTHOR_2],
        ]);
        $this->assertStatusCode($response, Response::HTTP_CREATED);

        $licence = $this->serializer->deserialize($response->getContent(), AssetLicence::class);

        $this->assertTrue($licence->getInternalRule()->isActive());
        $this->assertNotNull($licence->getInternalRule()->getMarkAsInternalSince());
        $this->assertSame('2025-01-01', $licence->getInternalRule()->getMarkAsInternalSince()->format('Y-m-d'));
        $this->assertCount(2, $licence->getInternalRuleAuthors());
        $this->assertContains(AuthorFixtures::AUTHOR_1, CollectionHelper::traversableToIds($licence->getInternalRuleAuthors()));
        $this->assertContains(AuthorFixtures::AUTHOR_2, CollectionHelper::traversableToIds($licence->getInternalRuleAuthors()));
    }

    /**
     * @throws SerializerException
     */
    public function testGetOneReturnsInternalRuleFields(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        // First create a licence with internal rule data.
        $createResponse = $client->post(AssetLicenceUrl::createPath(), [
            'name' => 'licence-get-one-internal',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'extId' => (string) Uuid::v7(),
            'internalRule' => [
                'active' => true,
                'markAsInternalSince' => '2025-06-15T12:00:00.000000Z',
            ],
            'internalRuleAuthors' => [AuthorFixtures::AUTHOR_1],
        ]);
        $this->assertStatusCode($createResponse, Response::HTTP_CREATED);

        $created = $this->serializer->deserialize($createResponse->getContent(), AssetLicence::class);

        // Now fetch it via GET.
        $response = $client->get(AssetLicenceUrl::getOne($created->getId()));
        $this->assertStatusCode($response, Response::HTTP_OK);

        $licence = $this->serializer->deserialize($response->getContent(), AssetLicence::class);

        $this->assertTrue($licence->getInternalRule()->isActive());
        $this->assertNotNull($licence->getInternalRule()->getMarkAsInternalSince());
        $this->assertCount(1, $licence->getInternalRuleAuthors());
        $this->assertContains(AuthorFixtures::AUTHOR_1, CollectionHelper::traversableToIds($licence->getInternalRuleAuthors()));
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateInternalRuleAndAuthors(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $existingLicence = self::getContainer()
            ->get(AssetLicenceRepository::class)
            ->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        // Update the licence with internal rule data.
        $response = $client->put(AssetLicenceUrl::update($existingLicence->getId()), [
            'id' => $existingLicence->getId(),
            'name' => $existingLicence->getName(),
            'extSystem' => $existingLicence->getExtSystem()->getId(),
            'extId' => $existingLicence->getExtId(),
            'internalRule' => [
                'active' => true,
                'markAsInternalSince' => '2025-03-01T00:00:00.000000Z',
            ],
            'internalRuleAuthors' => [AuthorFixtures::AUTHOR_1, AuthorFixtures::AUTHOR_2],
        ]);
        $this->assertStatusCode($response, Response::HTTP_OK);

        $licence = $this->serializer->deserialize($response->getContent(), AssetLicence::class);

        $this->assertTrue($licence->getInternalRule()->isActive());
        $this->assertNotNull($licence->getInternalRule()->getMarkAsInternalSince());
        $this->assertSame('2025-03-01', $licence->getInternalRule()->getMarkAsInternalSince()->format('Y-m-d'));
        $this->assertCount(2, $licence->getInternalRuleAuthors());
        $this->assertContains(AuthorFixtures::AUTHOR_1, CollectionHelper::traversableToIds($licence->getInternalRuleAuthors()));
        $this->assertContains(AuthorFixtures::AUTHOR_2, CollectionHelper::traversableToIds($licence->getInternalRuleAuthors()));
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateClearsInternalRuleAuthors(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        // Create a licence with internal rule authors.
        $createResponse = $client->post(AssetLicenceUrl::createPath(), [
            'name' => 'licence-clear-authors',
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'extId' => (string) Uuid::v7(),
            'internalRule' => ['active' => true],
            'internalRuleAuthors' => [AuthorFixtures::AUTHOR_1],
        ]);
        $this->assertStatusCode($createResponse, Response::HTTP_CREATED);

        $created = $this->serializer->deserialize($createResponse->getContent(), AssetLicence::class);
        $this->assertCount(1, $created->getInternalRuleAuthors());

        // Update with empty authors to clear them.
        $response = $client->put(AssetLicenceUrl::update($created->getId()), [
            'id' => $created->getId(),
            'name' => $created->getName(),
            'extSystem' => $created->getExtSystem()->getId(),
            'extId' => $created->getExtId(),
            'internalRule' => ['active' => false],
            'internalRuleAuthors' => [],
        ]);
        $this->assertStatusCode($response, Response::HTTP_OK);

        $updated = $this->serializer->deserialize($response->getContent(), AssetLicence::class);

        $this->assertFalse($updated->getInternalRule()->isActive());
        $this->assertCount(0, $updated->getInternalRuleAuthors());
    }

    /**
     * Tests internalRuleUsers CRUD at the domain level since DamUser is a
     * MappedSuperclass and cannot be deserialized via the API EntityIdHandler.
     */
    public function testUpdateInternalRuleUsersViaDomain(): void
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        $user = $this->entityManager->find(User::class, User::ID_ADMIN);

        $this->assertCount(0, $licence->getInternalRuleUsers());

        $licence->addInternalRuleUser($user);
        $this->entityManager->flush();

        $this->entityManager->refresh($licence);

        $this->assertCount(1, $licence->getInternalRuleUsers());
        $this->assertSame(User::ID_ADMIN, $licence->getInternalRuleUsers()->first()->getId());
    }
}
