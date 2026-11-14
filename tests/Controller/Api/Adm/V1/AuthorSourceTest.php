<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexManager;
use AnzuSystems\CoreDamBundle\Elasticsearch\IndexSettings;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Model\Enum\AuthorType;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AuthorUrl;
use Elastic\Elasticsearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AuthorSourceTest extends AbstractApiController
{
    public function testSourcePermissionsAndFlag(): void
    {
        $sourceId = $this->createAuthor('Managed Source', source: true);
        $authorId = $this->createAuthor('Managed Author', source: false);
        $this->enableAclCheck();
        $client = $this->getApiClient(User::ID_CMS_USER);

        $this->grantPermission(
            User::ID_CMS_USER,
            DamPermissions::DAM_AUTHOR_READ,
            DamPermissions::DAM_AUTHOR_CREATE,
            DamPermissions::DAM_AUTHOR_UPDATE,
            DamPermissions::DAM_AUTHOR_DELETE,
        );

        self::assertStatusCode($client->get(AuthorUrl::getOne($sourceId)), Response::HTTP_OK);
        self::assertStatusCode(
            $client->get(AuthorUrl::searchByExtSystem(ExtSystemFixtures::ID_CMS) . '?source=true'),
            Response::HTTP_OK
        );
        self::assertStatusCode($client->post(AuthorUrl::createPath(), $this->body('Denied Source', source: true)), Response::HTTP_FORBIDDEN);
        self::assertStatusCode(
            $client->put(AuthorUrl::update($sourceId), $this->body('Managed Source', source: true, id: $sourceId)),
            Response::HTTP_FORBIDDEN
        );
        self::assertStatusCode($client->delete(AuthorUrl::update($sourceId)), Response::HTTP_FORBIDDEN);

        $response = $client->put(AuthorUrl::update($authorId), $this->body('Managed Author', source: true, id: $authorId));
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'flags.source'));

        $this->grantPermission(
            User::ID_CMS_USER,
            DamPermissions::DAM_AUTHOR_READ,
            DamPermissions::DAM_SOURCE_CREATE,
            DamPermissions::DAM_SOURCE_UPDATE,
            DamPermissions::DAM_SOURCE_DELETE,
        );

        $response = $client->post(AuthorUrl::createPath(), $this->body('Created Source', source: true));
        self::assertStatusCode($response, Response::HTTP_CREATED);
        $createdId = json_decode((string) $response->getContent(), true)['id'];

        $response = $client->post(AuthorUrl::createPath(), $this->body('Typeless Source', source: true, type: AuthorType::None));
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'type'));

        $response = $client->put(AuthorUrl::update($createdId), $this->body('Renamed Source', source: true, id: $createdId));
        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame('Renamed Source', json_decode((string) $response->getContent(), true)['name']);

        $response = $client->put(AuthorUrl::update($createdId), $this->body('Renamed Source', source: false, id: $createdId));
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'flags.source'));

        $response = $client->put(AuthorUrl::update($createdId), $this->body('Renamed Source', source: true, id: $createdId, type: AuthorType::None));
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'type'));

        self::assertStatusCode(
            $client->put(AuthorUrl::update($authorId), $this->body('Managed Author', source: false, id: $authorId)),
            Response::HTTP_FORBIDDEN
        );
        self::assertStatusCode($client->delete(AuthorUrl::update($createdId)), Response::HTTP_NO_CONTENT);
    }

    public function testUpdateSavesHiddenInCredit(): void
    {
        $sourceId = $this->createAuthor('Hidden Source', source: true);

        $response = $this->getApiClient(User::ID_ADMIN)->put(
            AuthorUrl::update($sourceId),
            $this->body('Hidden Source', source: true, id: $sourceId, hiddenInCredit: true)
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertTrue(json_decode((string) $response->getContent(), true)['flags']['hiddenInCredit']);
        $this->entityManager->clear();
        self::assertTrue($this->entityManager->find(Author::class, $sourceId)->getFlags()->isHiddenInCredit());
    }

    /**
     * @param list<string> $expectedNames
     */
    #[DataProvider('sourceFilterDataProvider')]
    public function testSearchFiltersBySourceFlag(string $filter, array $expectedNames): void
    {
        $names = ['Kvasnicovy Autor', 'Kvasnicovy Zdroj'];
        $authors = [
            $this->createIndexedAuthor($names[0], source: false),
            $this->createIndexedAuthor($names[1], source: true),
        ];

        try {
            $response = $this->getApiClient(User::ID_ADMIN)->get(
                AuthorUrl::searchByExtSystem(ExtSystemFixtures::ID_CMS) . '?text=Kvasnicovy' . $filter
            );

            self::assertStatusCode($response, Response::HTTP_OK);
            $foundNames = array_column(json_decode((string) $response->getContent(), true)['data'], 'name');
            self::assertSame($expectedNames, array_values(array_intersect($names, $foundNames)));
        } finally {
            foreach ($authors as $author) {
                $this->getService(IndexManager::class)->delete($author, (string) $author->getId());
            }
        }
    }

    public static function sourceFilterDataProvider(): array
    {
        return [
            'Only sources' => [
                'filter' => '&source=true',
                'expectedNames' => ['Kvasnicovy Zdroj'],
            ],
            'Only authors' => [
                'filter' => '&source=false',
                'expectedNames' => ['Kvasnicovy Autor'],
            ],
            'Both without filter' => [
                'filter' => '',
                'expectedNames' => ['Kvasnicovy Autor', 'Kvasnicovy Zdroj'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(
        string $name,
        bool $source,
        ?string $id = null,
        AuthorType $type = AuthorType::Agency,
        bool $hiddenInCredit = false,
    ): array {
        return [
            ...(null === $id ? [] : ['id' => $id]),
            'name' => $name,
            'identifier' => $name,
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'type' => $type->toString(),
            'flags' => ['source' => $source, 'hiddenInCredit' => $hiddenInCredit],
        ];
    }

    private function createIndexedAuthor(string $name, bool $source): Author
    {
        $author = $this->entityManager->find(Author::class, $this->createAuthor($name, $source));
        self::assertInstanceOf(Author::class, $author);
        $this->getService(Client::class)->indices()->refresh([
            'index' => $this->getService(IndexSettings::class)->getFullIndexNameByEntity($author),
        ]);

        return $author;
    }

    /**
     * @return list<string>
     */
    private function getFieldErrors(Response $response, string $field): array
    {
        $content = json_decode((string) $response->getContent(), true);

        return $content['fields'][$field] ?? [];
    }

    private function createAuthor(string $name, bool $source): string
    {
        $response = $this->getApiClient(User::ID_ADMIN)->post(AuthorUrl::createPath(), $this->body($name, source: $source));
        self::assertStatusCode($response, Response::HTTP_CREATED);

        return json_decode((string) $response->getContent(), true)['id'];
    }
}
