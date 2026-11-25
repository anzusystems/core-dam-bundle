<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Exception\Handler\DependencyExistsExceptionHandler;
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
     * @param array{name: string, identifier?: string, extSystem: int, extId?: string} $requestJson
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
     * @return list<array{requestJson: array{name: string, identifier?: string, extSystem: int, extId?: string}, expectedResponseStatusCode: int}>
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
            [
                'requestJson' => [
                    'name' => 'test with identifier',
                    'identifier' => 'AB',
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
            'identifier' => 'b',
            'extSystem' => 0,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, [
            'name' => [
                ValidationException::ERROR_FIELD_LENGTH_MIN,
            ],
            'identifier' => [
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
        yield 'normalized variant' => [static fn (string $name): string => $name . '.'];
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

    public function testUpdateReviewedWithCurrentAuthorsFailure(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

        $response = $client->put(AuthorUrl::update($existingAuthor->getId()), [
            'id' => $existingAuthor->getId(),
            'name' => $existingAuthor->getName(),
            'extSystem' => $existingAuthor->getExtSystem()->getId(),
            'flags' => ['reviewed' => true],
            'currentAuthors' => [AuthorFixtures::AUTHOR_5],
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, [
            'flags.reviewed' => [
                ValidationException::ERROR_FIELD_INVALID,
            ],
        ]);
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateStoresSingleUseEnforced(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);

        $response = $client->put(AuthorUrl::update($existingAuthor->getId()), [
            'id' => $existingAuthor->getId(),
            'name' => $existingAuthor->getName(),
            'extSystem' => $existingAuthor->getExtSystem()->getId(),
            'flags' => ['singleUseEnforced' => true],
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $author = $this->serializer->deserialize($response->getContent(), Author::class);
        $this->assertTrue($author->getFlags()->isSingleUseEnforced());
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateStoresPriority(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AuthorUrl::createPath(), [
            'name' => 'Priority author',
            'extSystem' => ExtSystemFixtures::ID_CMS,
        ]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $created = $this->serializer->deserialize($response->getContent(), Author::class);
        $this->assertSame(0, $created->getPriority());

        $response = $client->put(AuthorUrl::update($created->getId()), [
            'id' => $created->getId(),
            'name' => $created->getName(),
            'extSystem' => $created->getExtSystem()->getId(),
            'priority' => 100,
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(100, $this->serializer->deserialize($response->getContent(), Author::class)->getPriority());
        $this->assertSame(100, $this->getAuthorFromDb($created->getId())->getPriority());
    }

    #[DataProvider('updatePriorityOutOfRangeDataProvider')]
    public function testUpdatePriorityOutOfRange(int $priority): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);
        $originalPriority = $existingAuthor->getPriority();

        $response = $client->put(AuthorUrl::update($existingAuthor->getId()), [
            'id' => $existingAuthor->getId(),
            'name' => $existingAuthor->getName(),
            'extSystem' => $existingAuthor->getExtSystem()->getId(),
            'priority' => $priority,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'priority' => [
                ValidationException::ERROR_FIELD_INVALID,
            ],
        ]);
        $this->assertSame($originalPriority, $this->getAuthorFromDb(AuthorFixtures::AUTHOR_1)->getPriority());
    }

    public static function updatePriorityOutOfRangeDataProvider(): array
    {
        return [
            'below the minimum' => [-1],
            'above the maximum' => [Author::PRIORITY_MAX + 1],
        ];
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateStoresBadge(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AuthorUrl::createPath(), [
            'name' => 'Badge author',
            'extSystem' => ExtSystemFixtures::ID_CMS,
        ]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $created = $this->serializer->deserialize($response->getContent(), Author::class);
        $this->assertSame('', $created->getBadge());

        foreach (['TASR', ''] as $badge) {
            $response = $client->put(AuthorUrl::update($created->getId()), [
                'id' => $created->getId(),
                'name' => $created->getName(),
                'extSystem' => $created->getExtSystem()->getId(),
                'badge' => $badge,
            ]);
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
            $this->assertSame($badge, $this->serializer->deserialize($response->getContent(), Author::class)->getBadge());
            $this->assertSame($badge, $this->getAuthorFromDb($created->getId())->getBadge());
        }
    }

    #[DataProvider('updateBadgeInvalidDataProvider')]
    public function testUpdateBadgeInvalid(string $badge, string $error): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $existingAuthor = self::getContainer()
            ->get(AuthorRepository::class)
            ->find(AuthorFixtures::AUTHOR_1);
        $originalBadge = $existingAuthor->getBadge();

        $response = $client->put(AuthorUrl::update($existingAuthor->getId()), [
            'id' => $existingAuthor->getId(),
            'name' => $existingAuthor->getName(),
            'extSystem' => $existingAuthor->getExtSystem()->getId(),
            'badge' => $badge,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'badge' => [
                $error,
            ],
        ]);
        $this->assertSame($originalBadge, $this->getAuthorFromDb(AuthorFixtures::AUTHOR_1)->getBadge());
    }

    public static function updateBadgeInvalidDataProvider(): array
    {
        return [
            'too long' => ['ABCDE', ValidationException::ERROR_FIELD_LENGTH_MAX],
            'lower case' => ['ab', ValidationException::ERROR_FIELD_INVALID],
            'diacritics' => ['ČTK', ValidationException::ERROR_FIELD_INVALID],
        ];
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

    public function testDeleteOfTheDefaultSourceOfALicenceIsRefused(): void
    {
        $this->makeDefaultSourceOfLicence(AuthorFixtures::AUTHOR_1, AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $response = $this->getApiClient(User::ID_ADMIN)->delete(AuthorUrl::delete(AuthorFixtures::AUTHOR_1));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $content = json_decode((string) $response->getContent(), true);
        self::assertSame(DependencyExistsExceptionHandler::ERROR, $content['error']);
        self::assertSame([AssetLicence::class], $content['dependencies']);
        $this->entityManager->clear();
        self::assertInstanceOf(Author::class, $this->entityManager->find(Author::class, AuthorFixtures::AUTHOR_1));
    }

    private function getAuthorFromDb(string $authorId): Author
    {
        $this->entityManager->clear();

        return $this->entityManager->find(Author::class, $authorId);
    }

    private function makeDefaultSourceOfLicence(string $authorId, int $licenceId): void
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, $licenceId);
        $licence->setDefaultSource($this->entityManager->find(Author::class, $authorId));
        $this->entityManager->flush();
    }
}
