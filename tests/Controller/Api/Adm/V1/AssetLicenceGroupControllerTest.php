<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup\AssetLicenceGroupFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceGroupRepository;
use AnzuSystems\CoreDamBundle\Tests\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures as TestAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetLicenceGroupUrl;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AssetLicenceGroupControllerTest extends AbstractApiController
{
    /**
     * @throws SerializerException
     */
    public function testGetOneSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceGroupUrl::getOne(AssetLicenceGroupFixtures::LICENCE_GROUP_ID));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $fromDb = self::getContainer()
            ->get(AssetLicenceGroupRepository::class)
            ->find(AssetLicenceGroupFixtures::LICENCE_GROUP_ID);

        $this->assertSame($fromDb->getId(), $assetLicence->getId());
        $this->assertSame($fromDb->getName(), $assetLicence->getName());
        $this->assertSame($fromDb->getExtSystem()->getId(), $assetLicence->getExtSystem()->getId());
    }

    /**
     * @throws SerializerException
     */
    public function testGetListSuccess(): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->get(AssetLicenceGroupUrl::getList());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $assetLicence = $this->serializer->deserialize(
            $response->getContent(),
            ApiInfiniteResponseList::class
        );

        $this->assertGreaterThan(0, count($assetLicence->getData()));
    }

    /**
     * @param array{name: string, extSystem: int, licences: int[]} $requestJson
     *
     * @throws SerializerException
     */
    #[DataProvider('createSuccessDataProvider')]
    public function testCreateSuccess(array $requestJson, int $expectedResponseStatusCode): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceGroupUrl::createPath(), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicenceGroup = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $this->assertSame($requestJson['name'], $assetLicenceGroup->getName());
        $this->assertSame($requestJson['extSystem'], $assetLicenceGroup->getExtSystem()->getId());
        $this->assertSame($requestJson['licences'], $assetLicenceGroup->getLicences()->map(
            fn (AssetLicence $licence): int => (int) $licence->getId()
        )->toArray());
    }

    /**
     * @return list<array{requestJson: array{name: string, extSystem: int, licences: int[]}, expectedResponseStatusCode: int}>
     */
    public static function createSuccessDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'name' => 'test',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'licences' => [TestAssetLicenceFixtures::LICENCE_ID],
                ],
                'expectedResponseStatusCode' => Response::HTTP_CREATED,
            ],
        ];
    }

    #[DataProvider('createFailureDataProvider')]
    public function testCreateFailure(array $requestJson, array $validationErrors): void
    {
        $client = $this->getApiClient(User::ID_ADMIN);

        $response = $client->post(AssetLicenceGroupUrl::createPath(), $requestJson);
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
            ],
            [
                'requestJson' => [
                    'name' => 'Group 100',
                    'extSystem' => 4,
                ],
                'validationErrors' => [
                    'name' => [
                        ValidationException::ERROR_FIELD_UNIQUE,
                    ],
                ],
            ],
            [
                'requestJson' => [
                    'name' => 'Group',
                    'extSystem' => 4,
                    'licences' => [AssetLicenceFixtures::DEFAULT_LICENCE_ID],
                ],
                'validationErrors' => [
                    'licences' => [
                        ValidationException::ERROR_FIELD_INVALID,
                    ],
                ],
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
        $response = $client->put(AssetLicenceGroupUrl::update($id), $requestJson);
        $this->assertSame($expectedResponseStatusCode, $response->getStatusCode());

        $assetLicenceGroup = $this->serializer->deserialize(
            $response->getContent(),
            AssetLicenceGroup::class
        );

        $this->assertSame($requestJson['name'], $assetLicenceGroup->getName());
        $this->assertSame($requestJson['extSystem'], $assetLicenceGroup->getExtSystem()->getId());
        $this->assertSame($requestJson['licences'], $assetLicenceGroup->getLicences()->map(
            fn (AssetLicence $licence): int => (int) $licence->getId()
        )->toArray());
    }

    public static function updateSuccessDataProvider(): array
    {
        return [
            [
                'requestJson' => [
                    'id' => AssetLicenceGroupFixtures::LICENCE_GROUP_ID,
                    'name' => 'test (updated)',
                    'extSystem' => ExtSystemFixtures::ID_BLOG,
                    'licences' => [TestAssetLicenceFixtures::LICENCE_ID, TestAssetLicenceFixtures::LICENCE_2_ID],
                ],
                'expectedResponseStatusCode' => Response::HTTP_OK,
            ],
        ];
    }

    /**
     * @throws SerializerException
     */
    public function testUpdateRejectsLicenceRemovalThatWouldEmptyAListView(): void
    {
        $group100 = $this->findGroup(AssetLicenceGroupFixtures::LICENCE_GROUP_ID);
        $licence = $this->findLicence(TestAssetLicenceFixtures::LICENCE_ID);
        $viewId = (int) $this->createListView('View with a single licence', [$group100], [$licence])->getId();

        $response = $this->getApiClient(User::ID_ADMIN)->put(AssetLicenceGroupUrl::update(AssetLicenceGroupFixtures::LICENCE_GROUP_ID), [
            'id' => AssetLicenceGroupFixtures::LICENCE_GROUP_ID,
            'name' => $group100->getName(),
            'extSystem' => ExtSystemFixtures::ID_BLOG,
            'licences' => [],
        ]);
        self::assertStatusCode($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(json_decode($response->getContent(), true), [
            'licences' => [AssetLicenceGroupFacade::ERROR_LICENCE_REQUIRED_BY_LIST_VIEW],
        ]);

        $this->entityManager->clear();
        self::assertTrue($this->findListView($viewId)->getLicences()->containsKey((int) $licence->getId()));
    }

    public function testUpdateCascadesLicenceRemovalOnlyToViewsUnreachableByOtherGroups(): void
    {
        $group100 = $this->findGroup(AssetLicenceGroupFixtures::LICENCE_GROUP_ID);
        $licence = $this->findLicence(TestAssetLicenceFixtures::LICENCE_ID);
        $keptLicence = $this->findLicence(TestAssetLicenceFixtures::LICENCE_2_ID);
        $this->attachLicenceToGroup($keptLicence, $group100);
        $secondGroup = $this->createLicenceGroup('Second group for cascade test', [$licence]);

        $viewOnGroup100OnlyId = (int) $this->createListView('View targeting only group 100', [$group100], [$licence, $keptLicence])->getId();
        $viewOnBothGroupsId = (int) $this->createListView('View targeting both groups', [$group100, $secondGroup], [$licence])->getId();
        $globalViewId = (int) $this->createListView('Global view', [], [$licence])->getId();

        $response = $this->getApiClient(User::ID_ADMIN)->put(AssetLicenceGroupUrl::update(AssetLicenceGroupFixtures::LICENCE_GROUP_ID), [
            'id' => AssetLicenceGroupFixtures::LICENCE_GROUP_ID,
            'name' => $group100->getName(),
            'extSystem' => ExtSystemFixtures::ID_BLOG,
            'licences' => [TestAssetLicenceFixtures::LICENCE_2_ID],
        ]);
        self::assertStatusCode($response, Response::HTTP_OK);

        $this->entityManager->clear();
        $reloadedGroup100Only = $this->findListView($viewOnGroup100OnlyId);
        self::assertFalse($reloadedGroup100Only->getLicences()->containsKey((int) $licence->getId()));
        self::assertTrue($reloadedGroup100Only->getLicences()->containsKey((int) $keptLicence->getId()));
        self::assertTrue($this->findListView($viewOnBothGroupsId)->getLicences()->containsKey((int) $licence->getId()));
        self::assertTrue($this->findListView($globalViewId)->getLicences()->containsKey((int) $licence->getId()));
    }

    public function testUpdateCascadesLicenceRemovalNullsUploadLicenceOfAffectedView(): void
    {
        $group100 = $this->findGroup(AssetLicenceGroupFixtures::LICENCE_GROUP_ID);
        $licence = $this->findLicence(TestAssetLicenceFixtures::LICENCE_ID);
        $keptLicence = $this->findLicence(TestAssetLicenceFixtures::LICENCE_2_ID);
        $this->attachLicenceToGroup($keptLicence, $group100);

        $view = $this->createListView('View using the removed licence as upload licence', [$group100], [$licence, $keptLicence]);
        $view->setUploadLicence($licence);
        $this->entityManager->flush();
        $viewId = (int) $view->getId();

        $response = $this->getApiClient(User::ID_ADMIN)->put(AssetLicenceGroupUrl::update(AssetLicenceGroupFixtures::LICENCE_GROUP_ID), [
            'id' => AssetLicenceGroupFixtures::LICENCE_GROUP_ID,
            'name' => $group100->getName(),
            'extSystem' => ExtSystemFixtures::ID_BLOG,
            'licences' => [TestAssetLicenceFixtures::LICENCE_2_ID],
        ]);
        self::assertStatusCode($response, Response::HTTP_OK);

        $this->entityManager->clear();
        $reloadedView = $this->findListView($viewId);
        self::assertNull($reloadedView->getUploadLicence());
        self::assertTrue($reloadedView->getLicences()->containsKey((int) $keptLicence->getId()));
    }

    private function findGroup(int $id): AssetLicenceGroup
    {
        /** @var AssetLicenceGroup $group */
        $group = $this->entityManager->find(AssetLicenceGroup::class, $id);

        return $group;
    }

    private function findLicence(int $id): AssetLicence
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(AssetLicence::class, $id);

        return $licence;
    }

    private function findListView(int $id): AssetListView
    {
        /** @var AssetListView $view */
        $view = $this->entityManager->find(AssetListView::class, $id);

        return $view;
    }

    private function attachLicenceToGroup(AssetLicence $licence, AssetLicenceGroup $group): void
    {
        $group->getLicences()->add($licence);
        $licence->getGroups()->add($group);
        $this->entityManager->flush();
    }

    /**
     * @param list<AssetLicence> $licences
     */
    private function createLicenceGroup(string $name, array $licences): AssetLicenceGroup
    {
        /** @var User $author */
        $author = $this->entityManager->find(User::class, User::ID_ADMIN);
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_BLOG);

        $group = (new AssetLicenceGroup())
            ->setName($name)
            ->setExtSystem($extSystem)
            ->setLicences(new ArrayCollection($licences))
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($author)
            ->setModifiedBy($author)
        ;
        foreach ($licences as $licence) {
            $licence->getGroups()->add($group);
        }
        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $group;
    }

    /**
     * @param list<AssetLicenceGroup> $groups
     * @param list<AssetLicence> $licences
     */
    private function createListView(string $name, array $groups, array $licences): AssetListView
    {
        /** @var User $author */
        $author = $this->entityManager->find(User::class, User::ID_ADMIN);
        /** @var ExtSystem $extSystem */
        $extSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_BLOG);

        $view = (new AssetListView())
            ->setName($name)
            ->setExtSystem($extSystem)
            ->setGroups(new ArrayCollection($groups))
            ->setLicences(new ArrayCollection($licences))
            ->setTypes([])
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($author)
            ->setModifiedBy($author)
        ;
        $this->entityManager->persist($view);
        $this->entityManager->flush();

        return $view;
    }
}
