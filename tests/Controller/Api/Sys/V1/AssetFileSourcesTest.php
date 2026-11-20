<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetFileSysUrl;
use AnzuSystems\CoreDamBundle\Tests\Domain\Author\AuthorTreeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetFileSourcesTest extends AbstractApiController
{
    use AuthorTreeTrait;

    private const string DOWNLOAD_URL = 'http://core-dam.sme.localhost/download-file?file=50x50&case=sources';
    private const string STORAGE_FILENAME = 'solid_image.jpeg';

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('rejectsSourcesDataProvider')]
    public function testRejectsSources(array $extra, bool $createBlogSource): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        if ($createBlogSource) {
            $this->createSource('Blog Listed Source', ExtSystemFixtures::ID_BLOG);
        }

        $response = $client->post(AssetFileSysUrl::createFromUrl(), $this->payload($extra));

        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $content = json_decode((string) $response->getContent(), true);
        self::assertSame([ValidationException::ERROR_FIELD_INVALID], $content['fields']['sources'] ?? []);
        self::assertNull($this->findAuthor('Getty Unknown'));
    }

    /**
     * @return array<string, array{extra: array<string, mixed>, createBlogSource: bool}>
     */
    public static function rejectsSourcesDataProvider(): array
    {
        return [
            'unknown name' => [
                'extra' => ['sources' => ['Getty Unknown']],
                'createBlogSource' => false,
            ],
            'author name' => [
                'extra' => ['sources' => ['Aarne Ormonde']],
                'createBlogSource' => false,
            ],
            'name that is not a string' => [
                'extra' => ['sources' => [100]],
                'createBlogSource' => false,
            ],
            'sources disabled in the ext system' => [
                'extra' => [
                    'licence' => AssetLicenceFixtures::LICENCE_ID,
                    'sources' => ['Blog Listed Source'],
                ],
                'createBlogSource' => true,
            ],
        ];
    }

    public function testRoutesAndRejectsSources(): void
    {
        $client = $this->getApiClient(User::ID_CMS_USER);
        $routed = $this->createSource('Routed Source');
        $listed = $this->createSource('Listed Source');

        $unknown = $client->post(AssetFileSysUrl::createFromUrl(), $this->payload(['sources' => ['Getty Unknown']]));
        self::assertStatusCode($unknown, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->findAuthor('Getty Unknown'));

        $response = $client->post(
            AssetFileSysUrl::createFromUrl(),
            $this->payload(['authors' => ['Routed Source', 'Photo Person'], 'sources' => ['Listed Source']])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        $assetId = json_decode((string) $response->getContent(), true)['assetId'];
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, $assetId);
        self::assertInstanceOf(Asset::class, $asset);
        self::assertSame(
            $this->sortedIds([$listed, $routed]),
            $this->sortedIds($asset->getSources()->toArray())
        );
        self::assertSame(['Photo Person'], $asset->getAuthors()->map(static fn (Author $author): string => $author->getName())->getValues());
    }

    /**
     * @param array<string, mixed> $extra
     * @param list<string> $expectedSources
     */
    #[DataProvider('defaultSourceDataProvider')]
    public function testGivesDefaultSourceOnlyWithoutAnotherSource(array $extra, array $expectedSources): void
    {
        $this->markDefaultSource();
        $this->createSource('Default Rival');

        $response = $this->getApiClient(User::ID_CMS_USER)->post(AssetFileSysUrl::createFromUrl(), $this->payload($extra));

        self::assertStatusCode($response, Response::HTTP_OK);
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, json_decode((string) $response->getContent(), true)['assetId']);
        self::assertInstanceOf(Asset::class, $asset);
        self::assertSame($expectedSources, $asset->getSources()->map(static fn (Author $source): string => $source->getName())->getValues());
    }

    public function testGivesDefaultSourceToFileFromStorage(): void
    {
        $this->markDefaultSource();
        $fileSystem = static::getContainer()->get(FileSystemProvider::class)->getFileSystemByStorageName('cms.image');
        $fileSystem->write(self::STORAGE_FILENAME, (string) file_get_contents(AbstractAssetFileFixtures::DATA_PATH . self::STORAGE_FILENAME));

        $payload = $this->payload(['path' => self::STORAGE_FILENAME]);
        unset($payload['url']);
        $response = $this->getApiClient(User::ID_CMS_USER)->post(AssetFileSysUrl::create(), $payload);
        $fileSystem->delete(self::STORAGE_FILENAME);

        self::assertStatusCode($response, Response::HTTP_OK);
        $this->entityManager->clear();
        $asset = $this->entityManager->find(Asset::class, json_decode((string) $response->getContent(), true)['assetId']);
        self::assertInstanceOf(Asset::class, $asset);
        self::assertSame(['AgencyA'], $asset->getSources()->map(static fn (Author $source): string => $source->getName())->getValues());
    }

    /**
     * @return array<string, array{extra: array<string, mixed>, expectedSources: list<string>}>
     */
    public static function defaultSourceDataProvider(): array
    {
        return [
            'without a source' => [
                'extra' => [],
                'expectedSources' => ['AgencyA'],
            ],
            'with a source' => [
                'extra' => ['sources' => ['Default Rival']],
                'expectedSources' => ['Default Rival'],
            ],
            'with an author that is a source' => [
                'extra' => ['authors' => ['Default Rival']],
                'expectedSources' => ['Default Rival'],
            ],
        ];
    }

    public function testAcceptsCustomDataOverFormLimit(): void
    {
        $title = str_repeat('T', 65);

        $response = $this->getApiClient(User::ID_CMS_USER)->post(
            AssetFileSysUrl::createFromUrl(),
            $this->payload(['customData' => ['title' => $title, 'headline' => 'Headline']])
        );

        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame($title, json_decode((string) $response->getContent(), true)['customData']['title']);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function payload(array $extra): array
    {
        return [
            'licence' => BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID,
            'url' => self::DOWNLOAD_URL,
            'customData' => [
                'title' => 'Titulok',
                'headline' => 'Headline',
            ],
            'generatePublicRoute' => false,
            ...$extra,
        ];
    }

    /**
     * @param list<Author> $authors
     *
     * @return list<string>
     */
    private function sortedIds(array $authors): array
    {
        $ids = array_map(static fn (Author $author): string => (string) $author->getId(), $authors);
        sort($ids);

        return $ids;
    }

    private function findAuthor(string $name): ?Author
    {
        return $this->entityManager->getRepository(Author::class)->findOneBy([
            'extSystem' => ExtSystemFixtures::ID_CMS,
            'name' => $name,
        ]);
    }
}
