<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AuthorUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AuthorControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

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
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AuthorUrl::searchByExtSystem(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($author->getData()));
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
    public static function createSuccessDataProvider(): array
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

    public function testCreateValidationFailure(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AuthorUrl::createPath(), [
            'name' => 'a',
            'extSystem' => 0,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, [
            'name' => [
                ValidationException::ERROR_FIELD_LENGTH_MIN,
            ],
            'extSystem' => [
                ValidationException::ERROR_FIELD_EMPTY,
            ],
        ]);
    }

    /**
     * @throws SerializerException
     */
    #[DataProvider('duplicateNameProvider')]
    public function testCreateDuplicateReturnsExisting(Closure $nameTransform): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

        $response = $client->post(AuthorUrl::createPath(), [
            'name' => $nameTransform($existingAuthor->getName()),
            'extSystem' => ExtSystemFixtures::ID_CMS,
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $author = $this->serializer->deserialize(
            $response->getContent(),
            Author::class
        );
        $this->assertSame($existingAuthor->getId(), $author->getId());
    }

    /**
     * @return iterable<string, array{Closure(string): string}>
     */
    public static function duplicateNameProvider(): iterable
    {
        yield 'same case' => [static fn (string $name): string => $name];
        // The duplicate check relies on a case-insensitive DB collation — pin that assumption.
        yield 'different case' => [static fn (string $name): string => mb_strtoupper($name)];
    }

    /**
     * @throws SerializerException
     */
    #[DataProvider('updateSuccessDataProvider')]
    public function testUpdateSuccess(array $requestJson, int $expectedResponseStatusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

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

    public static function updateSuccessDataProvider(): array
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
