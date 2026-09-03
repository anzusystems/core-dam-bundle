<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetListViewUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetListViewControllerTest extends AbstractApiController
{
    private const string EXISTING_VIEW_NAME = 'Existing view';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExistingView();
    }

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

        $listResponse = $client->get(AssetListViewUrl::getList());
        self::assertStatusCode($listResponse, Response::HTTP_OK);
        $listedIds = array_column(json_decode($listResponse->getContent(), true)['data'], 'id');
        self::assertContains($id, $listedIds);

        $updateJson = self::validRequestJson();
        $updateJson['id'] = $id;
        $updateJson['name'] = 'Mixed view (updated)';
        $updateJson['position'] = 3;
        $updateJson['groups'] = [];
        $updateJson['licences'] = [AssetLicenceFixtures::LICENCE_2_ID];
        $updateJson['uploadLicence'] = AssetLicenceFixtures::LICENCE_2_ID;
        $updateJson['types'] = [AssetType::IMAGE];
        $updateResponse = $client->put(AssetListViewUrl::update($id), $updateJson);
        self::assertStatusCode($updateResponse, Response::HTTP_OK);

        $reloaded = json_decode($client->get(AssetListViewUrl::getOne($id))->getContent(), true);
        self::assertSame('Mixed view (updated)', $reloaded['name']);
        self::assertSame(3, $reloaded['position']);
        self::assertSame([], $reloaded['groups']);
        self::assertSame([AssetLicenceFixtures::LICENCE_2_ID], $reloaded['licences']);
        self::assertSame(AssetLicenceFixtures::LICENCE_2_ID, $reloaded['uploadLicence']);
        self::assertSame([AssetType::IMAGE], $reloaded['types']);

        $deleteResponse = $client->delete(AssetListViewUrl::delete($id));
        self::assertStatusCode($deleteResponse, Response::HTTP_NO_CONTENT);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(AssetListView::class, $id));
    }

    /**
     * @throws SerializerException
     */
    public function testCreateWithValidUploadLicenceIsPersisted(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $requestJson = self::validRequestJson();
        $requestJson['uploadLicence'] = AssetLicenceFixtures::LICENCE_ID;

        $response = $client->post(AssetListViewUrl::createPath(), $requestJson);
        self::assertStatusCode($response, Response::HTTP_CREATED);

        $created = json_decode($response->getContent(), true);
        self::assertSame(AssetLicenceFixtures::LICENCE_ID, $created['uploadLicence']);

        $reloaded = json_decode($client->get(AssetListViewUrl::getOne((int) $created['id']))->getContent(), true);
        self::assertSame(AssetLicenceFixtures::LICENCE_ID, $reloaded['uploadLicence']);
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateChangingLicencesAndUploadLicenceTogetherSucceeds(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $createResponse = $client->post(AssetListViewUrl::createPath(), self::validRequestJson());
        self::assertStatusCode($createResponse, Response::HTTP_CREATED);
        $id = (int) json_decode($createResponse->getContent(), true)['id'];

        $updateJson = self::validRequestJson();
        $updateJson['id'] = $id;
        $updateJson['groups'] = [];
        $updateJson['licences'] = [AssetLicenceFixtures::LICENCE_2_ID];
        $updateJson['uploadLicence'] = AssetLicenceFixtures::LICENCE_2_ID;
        $updateResponse = $client->put(AssetListViewUrl::update($id), $updateJson);
        self::assertStatusCode($updateResponse, Response::HTTP_OK);

        $reloaded = json_decode($client->get(AssetListViewUrl::getOne($id))->getContent(), true);
        self::assertSame([AssetLicenceFixtures::LICENCE_2_ID], $reloaded['licences']);
        self::assertSame(AssetLicenceFixtures::LICENCE_2_ID, $reloaded['uploadLicence']);
    }

    /**
     * @param array{name: string, extSystem: int, groups: int[], licences: int[], uploadLicence?: int, types: string[]} $requestJson
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
     * @return list<array{requestJson: array{name: string, extSystem: int, groups: int[], licences: int[], uploadLicence?: int, types: string[]}, validationErrors: array<string, string[]>}>
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
            'group_from_other_ext_system' => [
                'requestJson' => [
                    'name' => 'Foreign group',
                    'extSystem' => ExtSystemFixtures::ID_CMS,
                    'groups' => [AssetLicenceGroupFixtures::LICENCE_GROUP_ID],
                    'licences' => [AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE],
                    'types' => [],
                ],
                'validationErrors' => [
                    'groups' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
            'short_name' => [
                'requestJson' => [
                    'name' => 'ab',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::LICENCE_ID],
                    'types' => [],
                ],
                'validationErrors' => [
                    'name' => [ValidationException::ERROR_FIELD_LENGTH_MIN],
                ],
            ],
            'duplicate_name_in_ext_system' => [
                'requestJson' => [
                    'name' => self::EXISTING_VIEW_NAME,
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::LICENCE_ID],
                    'types' => [],
                ],
                'validationErrors' => [
                    'name' => [ValidationException::ERROR_FIELD_UNIQUE],
                ],
            ],
            'upload_licence_outside_licences' => [
                'requestJson' => [
                    'name' => 'Bad upload licence',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::LICENCE_ID],
                    'uploadLicence' => AssetLicenceFixtures::LICENCE_2_ID,
                    'types' => [],
                ],
                'validationErrors' => [
                    'uploadLicence' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
            'position_out_of_smallint_range' => [
                'requestJson' => [
                    'name' => 'Far away',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'position' => 40_000,
                    'groups' => [],
                    'licences' => [AssetLicenceFixtures::LICENCE_ID],
                    'types' => [],
                ],
                'validationErrors' => [
                    'position' => [ValidationException::ERROR_FIELD_INVALID],
                ],
            ],
        ];
    }

    /**
     * @return array{name: string, extSystem: int, groups: int[], licences: int[], uploadLicence?: int, types: string[]}
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

    private function createExistingView(): void
    {
        /** @var ExtSystem $blogExtSystem */
        $blogExtSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_BLOG);
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);

        $view = (new AssetListView())
            ->setName(self::EXISTING_VIEW_NAME)
            ->setExtSystem($blogExtSystem)
            ->setLicences(new ArrayCollection([$licence]))
        ;
        $this->trackAsAdmin($view);
        $this->entityManager->persist($view);
        $this->entityManager->flush();
    }

    private function trackAsAdmin(AssetListView $entity): void
    {
        /** @var User $admin */
        $admin = $this->entityManager->find(User::class, User::ID_ADMIN);
        $entity
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($admin)
            ->setModifiedBy($admin)
        ;
    }
}
