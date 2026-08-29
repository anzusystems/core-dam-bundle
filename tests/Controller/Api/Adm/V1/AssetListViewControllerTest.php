<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetListViewUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetListViewControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testCrudLifecycle(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $createResponse = $client->post(AssetListViewUrl::createPath(), self::validRequestJson());
        self::assertStatusCode($createResponse, Response::HTTP_CREATED);
        $created = $this->serializer->deserialize($createResponse->getContent(), AssetListView::class);
        $id = (int) $created->getId();

        $getResponse = $client->get(AssetListViewUrl::getOne($id));
        self::assertStatusCode($getResponse, Response::HTTP_OK);

        $updateJson = self::validRequestJson();
        $updateJson['id'] = $id;
        $updateJson['name'] = 'Mixed view (updated)';
        $updateResponse = $client->put(AssetListViewUrl::update($id), $updateJson);
        self::assertStatusCode($updateResponse, Response::HTTP_OK);
        $updated = $this->serializer->deserialize($updateResponse->getContent(), AssetListView::class);
        self::assertSame('Mixed view (updated)', $updated->getName());

        $deleteResponse = $client->delete(AssetListViewUrl::delete($id));
        self::assertStatusCode($deleteResponse, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array{name: string, extSystem: int, groups: int[], licences: int[], types: string[]} $requestJson
     * @param array<string, string[]> $validationErrors
     */
    #[DataProvider('createFailureDataProvider')]
    public function testCreateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetListViewUrl::createPath(), $requestJson);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        $content = json_decode($response->getContent(), true);
        $this->assertValidationErrors($content, $validationErrors);
    }

    /**
     * @return list<array{requestJson: array{name: string, extSystem: int, groups: int[], licences: int[], types: string[]}, validationErrors: array<string, string[]>}>
     */
    public static function createFailureDataProvider(): array
    {
        return [
            'licence_outside_group_union' => [
                'requestJson' => [
                    'name' => 'Outside union',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [AssetLicenceGroupFixtures::LICENCE_GROUP_ID],
                    'licences' => [AssetLicenceFixtures::LICENCE_2_ID],
                    'types' => [],
                ],
                'validationErrors' => [
                    'licences' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
            'empty_licences' => [
                'requestJson' => [
                    'name' => 'No licences',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [],
                    'types' => [],
                ],
                'validationErrors' => [
                    'licences' => [ValidationException::ERROR_FIELD_RANGE_MIN],
                ],
            ],
            'licence_from_other_ext_system' => [
                'requestJson' => [
                    'name' => 'Foreign licence',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE],
                    'types' => [],
                ],
                'validationErrors' => [
                    'licences' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
            'unknown_type' => [
                'requestJson' => [
                    'name' => 'Bad type',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::LICENCE_ID],
                    'types' => ['unknown_type'],
                ],
                'validationErrors' => [
                    'types' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
        ];
    }

    /**
     * @return array{name: string, extSystem: int, groups: int[], licences: int[], types: string[]}
     */
    private static function validRequestJson(): array
    {
        return [
            'name' => 'Mixed view',
            'extSystem' => ExtSystemFixtures::ID_BLOG,
            'groups' => [AssetLicenceGroupFixtures::LICENCE_GROUP_ID],
            'licences' => [AssetLicenceFixtures::LICENCE_ID],
            'types' => [],
        ];
    }
}
