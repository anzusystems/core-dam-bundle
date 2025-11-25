<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\KeywordFixtures;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\KeywordUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class KeywordControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(KeywordUrl::getOne(KeywordFixtures::KEYWORD_1));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $keyword = $this->serializer->deserialize(
            $response->getContent(),
            Keyword::class
        );

        $fromDb = self::getContainer()
            ->get(KeywordRepository::class)
            ->find(KeywordFixtures::KEYWORD_1);

        $this->assertSame($fromDb->getId(), $keyword->getId());
        $this->assertSame($fromDb->getName(), $keyword->getName());
        $this->assertSame($fromDb->getExtSystem()->getId(), $keyword->getExtSystem()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testSearchByExtSystemSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(KeywordUrl::searchByExtSystem(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $keyword = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($keyword->getData()));
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

        $response = $client->post(KeywordUrl::createPath(), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $keyword = $this->serializer->deserialize(
            $response->getContent(),
            Keyword::class
        );

        $this->assertSame($requestJson['name'], $keyword->getName());
        $this->assertSame($requestJson['extSystem'], $keyword->getExtSystem()->getId());
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

    #[DataProvider('createFailureDataProvider')]
    public function testCreateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(KeywordUrl::createPath(), $requestJson);
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
                ],
            ]
        ];
    }

    #[DataProvider('keywordExistsDataProvider')]
    public function testKeywordExists(array $requestJson): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(KeywordUrl::createPath(), $requestJson);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public static function keywordExistsDataProvider(): array
    {
        $existingKeyword = self::getContainer()
            ->get(KeywordRepository::class)
            ->find(KeywordFixtures::KEYWORD_1);

        return [
            [
                'requestJson' => [
                    'name' => $existingKeyword->getName(),
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                ]
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
        $response = $client->put(KeywordUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $keyword = $this->serializer->deserialize(
            $response->getContent(),
            Keyword::class
        );

        $this->assertSame($requestJson['id'], $keyword->getId());
        $this->assertSame($requestJson['name'], $keyword->getName());
    }

    public static function updateSuccessDataProvider(): array
    {
        $existingKeyword = self::getContainer()
            ->get(KeywordRepository::class)
            ->find(KeywordFixtures::KEYWORD_1);

        return [
            [
                'requestJson' => [
                    'id' => $existingKeyword->getId(),
                    'name' => 'test (updated)',
                    'extSystem' => $existingKeyword->getExtSystem()->getId(),
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }
}
