<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\DataFixtures\AuthorFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Domain\Author\AuthorManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetMetadataSourcesTest extends AbstractApiController
{
    public function testSavesSourcesAndKeepsThemWhenMissing(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $assetId = $this->getImageAssetId();
        $source = $this->createAuthor('Saved Source', source: true);
        $sourceId = (string) $source->getId();

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $assetId,
            'described' => true,
            'authors' => [AuthorFixtures::AUTHOR_1],
            'sources' => [$sourceId],
        ]]);

        self::assertStatusCode($response, Response::HTTP_OK);
        $content = json_decode((string) $response->getContent(), true);
        self::assertSame([$sourceId], $content[0]['sources']);
        self::assertSame([$sourceId], $this->getSourceIds($assetId));
        self::assertSame([AuthorFixtures::AUTHOR_1], $this->getAuthorIds($assetId));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $assetId,
            'described' => true,
            'authors' => [AuthorFixtures::AUTHOR_2],
        ]]);

        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame([$sourceId], $this->getSourceIds($assetId));
        self::assertSame([AuthorFixtures::AUTHOR_2], $this->getAuthorIds($assetId));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $assetId,
            'sources' => [],
        ]]);

        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame([], $this->getSourceIds($assetId));
        self::assertSame([AuthorFixtures::AUTHOR_2], $this->getAuthorIds($assetId));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $this->getVideoAssetId(),
            'sources' => [],
        ]]);

        self::assertStatusCode($response, Response::HTTP_OK);
    }

    public function testRejectsWrongCollections(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $assetId = $this->getImageAssetId();
        $source = $this->createAuthor('Rejected Source', source: true);
        $sourceId = (string) $source->getId();
        $blogSource = $this->createAuthor('Blog Source', source: true, extSystemId: ExtSystemFixtures::ID_BLOG);

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'authors' => [$sourceId]]]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'authors'));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'sources' => [AuthorFixtures::AUTHOR_1]]]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'sources'));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'sources' => [(string) $blogSource->getId()]]]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'sources'));

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'described' => true, 'sources' => []]]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_EMPTY], $this->getFieldErrors($response, 'sources'));

        $videoAssetId = $this->getVideoAssetId();
        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $videoAssetId, 'authors' => [$sourceId]]]);
        self::assertStatusCode($response, Response::HTTP_OK);

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $videoAssetId, 'sources' => [$sourceId]]]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $this->getFieldErrors($response, 'sources'));

        self::assertSame([], $this->getSourceIds($assetId));

        $storedSource = $this->createAuthor('Stored Source', source: false);
        $storedSourceId = (string) $storedSource->getId();
        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'authors' => [$storedSourceId]]]);
        self::assertStatusCode($response, Response::HTTP_OK);
        $this->markSource($storedSourceId);

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $assetId,
            'authors' => [$storedSourceId, AuthorFixtures::AUTHOR_1],
        ]]);
        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame([$storedSourceId], $this->getSourceIds($assetId));
        self::assertSame([AuthorFixtures::AUTHOR_1], $this->getAuthorIds($assetId));
    }

    #[DataProvider('sentSourcesDataProvider')]
    public function testKeepsStoredSourceMovedFromAuthorsWhenSourcesAreSent(bool $sendListedSource): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);
        $assetId = $this->getImageAssetId();
        $storedSourceId = (string) $this->createAuthor('Stored Sent Source', source: false)->getId();
        $listedSourceId = (string) $this->createAuthor('Listed Sent Source', source: true)->getId();
        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [['id' => $assetId, 'authors' => [$storedSourceId]]]);
        self::assertStatusCode($response, Response::HTTP_OK);
        $this->markSource($storedSourceId);
        $sentSourceIds = $sendListedSource ? [$listedSourceId] : [];

        $response = $client->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $assetId,
            'authors' => [$storedSourceId, AuthorFixtures::AUTHOR_1],
            'sources' => $sentSourceIds,
        ]]);

        self::assertStatusCode($response, Response::HTTP_OK);
        $expectedSourceIds = [$storedSourceId, ...$sentSourceIds];
        sort($expectedSourceIds);
        self::assertSame($expectedSourceIds, $this->getSourceIds($assetId));
        self::assertSame([AuthorFixtures::AUTHOR_1], $this->getAuthorIds($assetId));
    }

    public static function sentSourcesDataProvider(): array
    {
        return [
            'Empty sources from the admin form' => [
                'sendListedSource' => false,
            ],
            'Another source from the admin form' => [
                'sendListedSource' => true,
            ],
        ];
    }

    /**
     * @param list<string> $permissions
     */
    #[DataProvider('sourcePermissionDataProvider')]
    public function testSourcesRequireAuthorRead(array $permissions, int $expectedStatus): void
    {
        $sourceId = (string) $this->createAuthor('Permission Source', source: true)->getId();
        $this->enableAclCheck();
        $this->grantPermission(User::ID_CMS_USER, ...$permissions);

        $response = $this->getApiClient(User::ID_CMS_USER)->patch(AssetUrl::metadataBulkUpdate(), [[
            'id' => $this->getImageAssetId(),
            'sources' => [$sourceId],
        ]]);

        self::assertStatusCode($response, $expectedStatus);
    }

    public static function sourcePermissionDataProvider(): array
    {
        return [
            'Without author read' => [
                'permissions' => [DamPermissions::DAM_ASSET_UPDATE],
                'expectedStatus' => Response::HTTP_FORBIDDEN,
            ],
            'With author read' => [
                'permissions' => [DamPermissions::DAM_ASSET_UPDATE, DamPermissions::DAM_AUTHOR_READ],
                'expectedStatus' => Response::HTTP_OK,
            ],
        ];
    }

    private function createAuthor(string $name, bool $source, int $extSystemId = ExtSystemFixtures::ID_CMS): Author
    {
        $extSystem = $this->entityManager->find(ExtSystem::class, $extSystemId);
        $author = (new Author())
            ->setName($name)
            ->setIdentifier($name)
            ->setExtSystem($extSystem);
        $author->getFlags()->setSource($source);

        return $this->getService(AuthorManager::class)->create($author);
    }

    private function markSource(string $authorId): void
    {
        $this->entityManager->clear();
        $author = $this->entityManager->find(Author::class, $authorId);
        self::assertInstanceOf(Author::class, $author);
        $this->getService(AuthorManager::class)->markSource($author);
    }

    private function getImageAssetId(): string
    {
        $image = $this->entityManager->find(ImageFile::class, ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $image);

        return (string) $image->getAsset()->getId();
    }

    private function getVideoAssetId(): string
    {
        $video = $this->entityManager->find(VideoFile::class, VideoFixtures::VIDEO_ID_1);
        self::assertInstanceOf(VideoFile::class, $video);

        return (string) $video->getAsset()->getId();
    }

    /**
     * @return list<string>
     */
    private function getFieldErrors(Response $response, string $field): array
    {
        $content = json_decode((string) $response->getContent(), true);

        return $content['fields']['[0].' . $field] ?? [];
    }

    /**
     * @return list<string>
     */
    private function getSourceIds(string $assetId): array
    {
        return $this->getCollectionIds($assetId, 'getSources');
    }

    /**
     * @return list<string>
     */
    private function getAuthorIds(string $assetId): array
    {
        return $this->getCollectionIds($assetId, 'getAuthors');
    }

    /**
     * @return list<string>
     */
    private function getCollectionIds(string $assetId, string $getter): array
    {
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, $assetId);
        $ids = $asset->{$getter}()->map(static fn (Author $author): string => (string) $author->getId())->getValues();
        sort($ids);

        return $ids;
    }
}
