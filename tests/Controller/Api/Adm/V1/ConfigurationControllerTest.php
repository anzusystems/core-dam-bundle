<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\ConfigurationUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class ConfigurationControllerTest extends AbstractApiController
{
    /**
     * @param array{enabled: bool, required: bool, creditFormat: string, creditSeparator: string} $expectedSources
     */
    #[DataProvider('extSystemSourcesDataProvider')]
    public function testExtSystemShowsSourcesOfAssetType(int $extSystemId, string $assetType, array $expectedSources): void
    {
        $response = $this->getApiClient(User::ID_ADMIN)->get(ConfigurationUrl::getExtSystem($extSystemId));

        self::assertStatusCode($response, Response::HTTP_OK);
        self::assertSame($expectedSources, json_decode((string) $response->getContent(), true)[$assetType]['sources']);
    }

    public function testSourcesCreditFormat(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $setResponse = $client->get(ConfigurationUrl::getExtSystem(ExtSystemFixtures::ID_CMS));
        self::assertStatusCode($setResponse, Response::HTTP_OK);
        $set = json_decode((string) $setResponse->getContent(), true);
        self::assertSame('{authors} / {sources}', $set['image']['sources']['creditFormat']);
        self::assertSame('; ', $set['image']['sources']['creditSeparator']);

        $defaultResponse = $client->get(ConfigurationUrl::getExtSystem(ExtSystemFixtures::ID_BLOG));
        self::assertStatusCode($defaultResponse, Response::HTTP_OK);
        $default = json_decode((string) $defaultResponse->getContent(), true);
        self::assertSame('{sources}/{authors}', $default['image']['sources']['creditFormat']);
        self::assertSame(', ', $default['image']['sources']['creditSeparator']);
    }

    public static function extSystemSourcesDataProvider(): array
    {
        return [
            'Image with sources required in admin' => [
                'extSystemId' => ExtSystemFixtures::ID_CMS,
                'assetType' => 'image',
                'expectedSources' => [
                    'enabled' => true,
                    'required' => true,
                    'creditFormat' => '{authors} / {sources}',
                    'creditSeparator' => '; ',
                ],
            ],
            'Video without sources' => [
                'extSystemId' => ExtSystemFixtures::ID_CMS,
                'assetType' => 'video',
                'expectedSources' => [
                    'enabled' => false,
                    'required' => false,
                    'creditFormat' => '{sources}/{authors}',
                    'creditSeparator' => ', ',
                ],
            ],
            'Image of an ext system without sources' => [
                'extSystemId' => ExtSystemFixtures::ID_BLOG,
                'assetType' => 'image',
                'expectedSources' => [
                    'enabled' => false,
                    'required' => false,
                    'creditFormat' => '{sources}/{authors}',
                    'creditSeparator' => ', ',
                ],
            ],
        ];
    }
}
