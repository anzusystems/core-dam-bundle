<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\AuthorCleanPhraseProcessor;
use AnzuSystems\CoreDamBundle\Domain\AuthorCleanPhrase\Cache\AuthorCleanPhraseCache;
use AnzuSystems\CoreDamBundle\Entity\AuthorCleanPhrase;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseMode;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorCleanPhraseType;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AuthorCleanPhraseUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Response;

final class AuthorCleanPhraseControllerTest extends AbstractApiController
{
    private readonly AuthorCleanPhraseProcessor $authorCleanPhraseProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorCleanPhraseProcessor = $this->getService(AuthorCleanPhraseProcessor::class);

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        /** @var AuthorCleanPhraseCache $cache */
        $cache = static::getContainer()->get(AuthorCleanPhraseCache::class);
        $cache->cleanCache();
    }

    /**
     * @dataProvider createFailedDataProvider
     */
    public function testCreateFailed(
        array $phrasePayload,
        array $validationErrors,
    ): void {
        $client = $this->getApiClient(User::ID_ADMIN);
        $response = $client->post(AuthorCleanPhraseUrl::create(), $phrasePayload);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    private function createFailedDataProvider(): array
    {
        return [
            [
                'phrasePayload' => [
                    'extSystem' => null,
                    'phrase' => '',
                    'type' => AuthorCleanPhraseType::Regex->value,
                    'mode' => AuthorCleanPhraseMode::Split->value,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'validationErrors' => [
                    'mode' => [
                        ValidationException::ERROR_FIELD_INVALID,
                    ],
                    'phrase' => [
                        ValidationException::ERROR_FIELD_LENGTH_MIN,
                    ],
                    'extSystem' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ]
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => '(c)',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Remove->value,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'validationErrors' => [
                    'phrase' => [
                        ValidationException::ERROR_FIELD_UNIQUE,
                    ],
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => 'Ajajaj',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Replace->value,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'validationErrors' => [
                    'authorReplacement' => [
                        ValidationException::ERROR_FIELD_EMPTY,
                    ],
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'phrase' => 'Ajajaj',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Replace->value,
                    'authorReplacement' => AuthorFixtures::AUTHOR_1,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'validationErrors' => [
                    'authorReplacement' => [
                        ValidationException::ERROR_INVALID_EXT_SYSTEM
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(
        array $phrasePayload,
        string $testAuthorString,
        array $expectedProcessResultBefore,
        array $expectedProcessResultAfter,
    ): void {
        $client = $this->getApiClient(User::ID_ADMIN);

        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, $phrasePayload['extSystem']);

        $result = $this->authorCleanPhraseProcessor->processString($testAuthorString, $extSystem);
        $this->assertSame($expectedProcessResultBefore['authorNames'], $result->getAuthorNames());
        $this->assertSame($expectedProcessResultBefore['authors'], CollectionHelper::traversableToIds($result->getAuthors()));

        $response = $client->post(AuthorCleanPhraseUrl::create(), $phrasePayload);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $phrase = $this->serializer->deserialize($response->getContent(), AuthorCleanPhrase::class);

        $this->assertSame($phrasePayload['phrase'], $phrase->getPhrase());
        $this->assertSame($phrasePayload['extSystem'], $phrase->getExtSystem()->getId());
        $this->assertSame($phrasePayload['type'], $phrase->getType()->value);
        $this->assertSame($phrasePayload['mode'], $phrase->getMode()->value);
        $this->assertSame($phrasePayload['flags']['wordBoundary'], $phrase->getFlags()->isWordBoundary());

        $result = $this->authorCleanPhraseProcessor->processString($testAuthorString, $extSystem);
        $this->assertSame($expectedProcessResultAfter['authorNames'], $result->getAuthorNames());
        $this->assertSame($expectedProcessResultAfter['authors'], CollectionHelper::traversableToIds($result->getAuthors()));
    }

    private function createDataProvider(): array
    {
        return [
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => 'test',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Remove->value,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'testAuthorString' => 'test',
                'expectedProcessResultBefore' => [
                    'authorNames' => ['test'],
                    'authors' => [],
                ],
                'expectedProcessResultAfter' => [
                    'authorNames' => [],
                    'authors' => [],
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => '?',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Split->value,
                    'flags' => [
                        'wordBoundary' => false,
                    ],
                ],
                'testAuthorString' => 'test ? result',
                'expectedProcessResultBefore' => [
                    'authorNames' => ['test ? result'],
                    'authors' => [],
                ],
                'expectedProcessResultAfter' => [
                    'authorNames' => ['test', 'result'],
                    'authors' => [],
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => '\d+',
                    'type' => AuthorCleanPhraseType::Regex->value,
                    'mode' => AuthorCleanPhraseMode::Remove->value,
                    'flags' => [
                        'wordBoundary' => false,
                    ],
                ],
                'testAuthorString' => '12345aa',
                'expectedProcessResultBefore' => [
                    'authorNames' => ['12345aa'],
                    'authors' => [],
                ],
                'expectedProcessResultAfter' => [
                    'authorNames' => ['aa'],
                    'authors' => [],
                ]
            ],
            [
                'phrasePayload' => [
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'phrase' => 'Larry Queen',
                    'type' => AuthorCleanPhraseType::Word->value,
                    'mode' => AuthorCleanPhraseMode::Replace->value,
                    'authorReplacement' => AuthorFixtures::AUTHOR_1,
                    'flags' => [
                        'wordBoundary' => true,
                    ],
                ],
                'testAuthorString' => 'Larry Queen the king',
                'expectedProcessResultBefore' => [
                    'authorNames' => ['Larry Queen the king'],
                    'authors' => [],
                ],
                'expectedProcessResultAfter' => [
                    'authorNames' => ['the king'],
                    'authors' => [AuthorFixtures::AUTHOR_1],
                ]
            ]
        ];
    }

    /**
     * @throws SerializerException
     */
    public function testList(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AuthorCleanPhraseUrl::getList(ExtSystemFixtures::ID_CMS));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $phrases = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($phrases->getData()));
    }

    /**
     * @throws SerializerException
     */
    public function testDelete(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        /** @var AuthorCleanPhrase $phrase */
        $phrase = $this->entityManager->getRepository(AuthorCleanPhrase::class)->findBy(
            criteria: ['extSystem' => ExtSystemFixtures::ID_CMS, 'phrase' => '(c)'],
            limit: 1
        )[0];

        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);

        $result = $this->authorCleanPhraseProcessor->processString('(c)', $extSystem);
        $this->assertSame([], $result->getAuthorNames());

        $response = $client->delete(AuthorCleanPhraseUrl::getOne((int) $phrase->getId()));
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $result = $this->authorCleanPhraseProcessor->processString('(c)', $extSystem);
        $this->assertSame(['(c)'], $result->getAuthorNames());
    }

    /**
     * @throws SerializerException
     */
    public function testUpdate(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        /** @var AuthorCleanPhrase $phrase */
        $phrase = $this->entityManager->getRepository(AuthorCleanPhrase::class)->findBy(
            criteria: ['extSystem' => ExtSystemFixtures::ID_CMS, 'phrase' => '(c)'],
            limit: 1
        )[0];

        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_CMS);

        $result = $this->authorCleanPhraseProcessor->processString('(c); Kopyto', $extSystem);
        $this->assertSame(['Kopyto'], $result->getAuthorNames());

        $response = $client->put(AuthorCleanPhraseUrl::getOne((int) $phrase->getId()), [
            'id' => $phrase->getId(),
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'phrase' => 'Kopyto',
            'type' => AuthorCleanPhraseType::Word->value,
            'mode' => AuthorCleanPhraseMode::Remove->value,
            'flags' => [
                'wordBoundary' => true,
            ],
        ]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $result = $this->authorCleanPhraseProcessor->processString('(c); Kopyto', $extSystem);
        $this->assertSame(['(c)'], $result->getAuthorNames());
    }
}
