<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiControllerTest;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AuthorUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class AuthorControllerTest extends AbstractApiControllerTest
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(AuthorUrl::getOne(AuthorFixtures::AUTHOR_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            Author::class
        );

        $fromDb = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

        $this->assertSame($fromDb->getId(), $author->getId());
        $this->assertSame($fromDb->getName(), $author->getName());
        $this->assertSame($fromDb->getExtSystem()->getId(), $author->getExtSystem()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testSearchByExtSystemSuccess(): void
    {
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->get(AuthorUrl::searchByExtSystem(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($author->getData()));
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
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(AuthorUrl::createPath(), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            Author::class
        );

        $this->assertSame($requestJson['name'], $author->getName());
        $this->assertSame($requestJson['extSystem'], $author->getExtSystem()->getId());
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
        $client = $this->getClient(User::ID_ADMIN);

        $response = $client->post(AuthorUrl::createPath(), $requestJson);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    public function createFailureDataProvider(): array
    {
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

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
                    'name' => $existingAuthor->getName(),
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                ],
                'validationErrors' => [
                    'identifier' => [
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
        $client = $this->getClient(User::ID_ADMIN);

        $id = $requestJson['id'];
        $response = $client->put(AuthorUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            Author::class
        );

        $this->assertSame($requestJson['id'], $author->getId());
        $this->assertSame($requestJson['name'], $author->getName());
    }

    public function updateSuccessDataProvider(): array
    {
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

        return [
            [
                'requestJson' => [
                    'id' => $existingAuthor->getId(),
                    'name' => 'test (updated)',
                    'extSystem' => $existingAuthor->getExtSystem()->getId(),
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }
}
