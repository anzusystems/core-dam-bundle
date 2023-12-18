<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ExtSystemUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class ExtSystemControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(ExtSystemUrl::getOne(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $extSystem = $this->serializer->deserialize(
            $response->getContent(),
            ExtSystem::class
        );

        $extSystemRepository = self::getContainer()->get(ExtSystemRepository::class);
        $fromDb = $extSystemRepository->find(ExtSystemFixtures::ID_CMS);

        $this->assertSame($fromDb->getId(), $extSystem->getId());
        $this->assertSame($fromDb->getName(), $extSystem->getName());
        $this->assertSame($fromDb->getSlug(), $extSystem->getSlug());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(ExtSystemUrl::getList());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($assetLicence->getData()));
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
        $response = $client->put(ExtSystemUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $extSystem = $this->serializer->deserialize(
            $response->getContent(),
            ExtSystem::class
        );

        $this->assertSame($requestJson['id'], $extSystem->getId());
        $this->assertSame($requestJson['name'], $extSystem->getName());
        $this->assertSame($requestJson['adminUsers'], CollectionHelper::traversableToIds($extSystem->getAdminUsers()));
    }

    public function updateSuccessDataProvider(): array
    {
        $existingExtSystem = self::getContainer()
            ->get(ExtSystemRepository::class)
            ->find(ExtSystemFixtures::ID_CMS);

        return [
            [
                'requestJson' => [
                    'id' => $existingExtSystem->getId(),
                    'name' => 'test (updated)',
                    'slug' => $existingExtSystem->getSlug(),
                    'adminUsers' => [User::ID_ADMIN],
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }

    /**
     * @dataProvider updateFailureDataProvider
     */
    public function testUpdateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $id = $requestJson['id'];
        $response = $client->put(ExtSystemUrl::update($id), $requestJson);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    public function updateFailureDataProvider(): array
    {
        $existingExtSystem = self::getContainer()
            ->get(ExtSystemRepository::class)
            ->find(ExtSystemFixtures::ID_CMS);

        return [
            [
                'requestJson' => [
                    'id' => $existingExtSystem->getId(),
                    'name' => 'a',
                    'slug' => 'a',
                ],
                'validationErrors' => [
                    'name' => [
                        ValidationException::ERROR_FIELD_LENGTH_MIN,
                    ],
                    'slug' => [
                        ValidationException::ERROR_FIELD_LENGTH_MIN,
                    ],
                ],
            ],
        ];
    }
}
