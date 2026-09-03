<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetListView;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AssetListView\AssetListViewResolver;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Domain\AssetListView\AssetListViewScope;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\AssetLicenceGroupFixtures;
use AnzuSystems\CoreDamBundle\Tests\Data\Fixtures\ExtSystemFixtures;
use Doctrine\Common\Collections\ArrayCollection;

final class AssetListViewResolverTest extends CoreDamKernelTestCase
{
    private const int USER_MEMBER_ID = 900_101;
    private const int USER_OUTSIDER_ID = 900_102;
    private const int USER_GLOBAL_ONLY_ID = 900_103;
    private const int USER_SUPER_ADMIN_ID = 900_104;
    private const int SECOND_GROUP_ID = 900_105;

    private AssetListViewResolver $resolver;
    private UserManager $userManager;
    private User $userMember;
    private User $userOutsider;
    private User $userGlobalOnly;
    private User $userSuperAdmin;
    private AssetListView $targetedView;
    private AssetListView $targetedFirstView;
    private AssetListView $foreignLicenceView;
    private AssetListView $globalViewA;
    private AssetListView $globalViewB;
    private AssetListView $mixedUploadLicenceView;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->getService(AssetListViewResolver::class);
        $this->userManager = $this->getService(UserManager::class);

        /** @var User $author */
        $author = $this->entityManager->find(User::class, User::ID_ADMIN);
        $this->author = $author;

        /** @var ExtSystem $blogExtSystem */
        $blogExtSystem = $this->entityManager->find(ExtSystem::class, ExtSystemFixtures::ID_BLOG);
        /** @var AssetLicenceGroup $group100 */
        $group100 = $this->entityManager->find(AssetLicenceGroup::class, AssetLicenceGroupFixtures::LICENCE_GROUP_ID);
        /** @var AssetLicence $licenceInGroup */
        $licenceInGroup = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_ID);
        /** @var AssetLicence $licenceOutsideGroup */
        $licenceOutsideGroup = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::LICENCE_2_ID);

        /** @var AssetLicence $foreignLicence */
        $foreignLicence = $this->entityManager->find(AssetLicence::class, AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE);
        $secondGroup = $this->createGroup(self::SECOND_GROUP_ID, 'Second group', $blogExtSystem, [$licenceInGroup]);

        $this->userMember = $this->createUser(self::USER_MEMBER_ID, 'member@anzusystems.sk', licenceGroups: new ArrayCollection([$group100, $secondGroup]));
        $this->userOutsider = $this->createUser(self::USER_OUTSIDER_ID, 'outsider@anzusystems.sk');
        $this->userGlobalOnly = $this->createUser(self::USER_GLOBAL_ONLY_ID, 'global-only@anzusystems.sk', userToExtSystems: new ArrayCollection([$blogExtSystem]));
        $this->userSuperAdmin = $this->createUser(self::USER_SUPER_ADMIN_ID, 'super-admin@anzusystems.sk', roles: [AnzuUser::ROLE_SUPER_ADMIN]);

        $this->targetedView = $this->createView('Targeted at both groups', $blogExtSystem, [$group100, $secondGroup], [$licenceInGroup]);
        $this->targetedFirstView = $this->createView('Targeted first by position', $blogExtSystem, [$group100], [$licenceInGroup], position: -1);
        $this->globalViewA = $this->createView('Global - reachable licence', $blogExtSystem, [], [$licenceInGroup]);
        $this->globalViewB = $this->createView('Global - unreachable licence', $blogExtSystem, [], [$licenceOutsideGroup]);
        $this->foreignLicenceView = $this->createView('Global - licence moved to another ext system', $blogExtSystem, [], [$licenceInGroup, $foreignLicence]);
        $this->mixedUploadLicenceView = $this->createView(
            'Global - upload licence not granted to every viewer',
            $blogExtSystem,
            [],
            [$licenceInGroup, $licenceOutsideGroup],
            uploadLicence: $licenceOutsideGroup,
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        // The resolver works on hydrated users; entities built in memory carry uninitialized collections.
        $this->userMember = $this->reloadUser(self::USER_MEMBER_ID);
        $this->userOutsider = $this->reloadUser(self::USER_OUTSIDER_ID);
        $this->userGlobalOnly = $this->reloadUser(self::USER_GLOBAL_ONLY_ID);
        $this->userSuperAdmin = $this->reloadUser(self::USER_SUPER_ADMIN_ID);
    }

    public function testTargetedViewIsVisibleOnlyToGroupMember(): void
    {
        $memberViewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));
        $outsiderViewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userOutsider));

        self::assertContains((int) $this->targetedView->getId(), $memberViewIds);
        self::assertNotContains((int) $this->targetedView->getId(), $outsiderViewIds);
    }

    public function testViewWithoutGroupsAppliesToEveryUserOfTheExtSystem(): void
    {
        $memberViewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));
        $globalOnlyViewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userGlobalOnly));

        self::assertContains((int) $this->globalViewA->getId(), $memberViewIds);
        self::assertContains((int) $this->globalViewA->getId(), $globalOnlyViewIds);
    }

    public function testViewWithEmptyGrantedIntersectionIsExcluded(): void
    {
        $memberViewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));

        self::assertNotContains((int) $this->globalViewB->getId(), $memberViewIds);
    }

    public function testTargetedScopesPrecedeGlobalScopes(): void
    {
        $viewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));

        $targetedPosition = array_search((int) $this->targetedView->getId(), $viewIds, true);
        $globalPosition = array_search((int) $this->globalViewA->getId(), $viewIds, true);

        self::assertIsInt($targetedPosition);
        self::assertIsInt($globalPosition);
        self::assertLessThan($globalPosition, $targetedPosition);
    }

    public function testSuperAdminGetsEveryLicenceOfTheViewWithoutOwnRights(): void
    {
        $scopes = $this->scopesByViewId($this->resolver->resolveForUser($this->userSuperAdmin));

        self::assertSame([AssetLicenceFixtures::LICENCE_2_ID], $scopes[(int) $this->globalViewB->getId()]->getLicenceIds());
    }

    public function testLicenceFromAnotherExtSystemIsFilteredOutOfTheScope(): void
    {
        $scopes = $this->scopesByViewId($this->resolver->resolveForUser($this->userSuperAdmin));

        self::assertSame([AssetLicenceFixtures::LICENCE_ID], $scopes[(int) $this->foreignLicenceView->getId()]->getLicenceIds());
    }

    public function testTargetedViewsAreOrderedByPosition(): void
    {
        $viewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));

        self::assertSame([(int) $this->targetedFirstView->getId(), (int) $this->targetedView->getId()], array_slice($viewIds, 0, 2));
    }

    public function testViewTargetedByTwoGroupsOfTheUserIsReturnedOnce(): void
    {
        $viewIds = $this->scopeViewIds($this->resolver->resolveForUser($this->userMember));

        self::assertSame(1, count(array_keys($viewIds, (int) $this->targetedView->getId(), true)));
    }

    public function testUploadLicenceIdIsNullWhenUserHasNoRightToIt(): void
    {
        $scopes = $this->scopesByViewId($this->resolver->resolveForUser($this->userMember));

        self::assertNull($scopes[(int) $this->mixedUploadLicenceView->getId()]->getUploadLicenceId());
    }

    public function testUploadLicenceIdIsReturnedWhenUserHasRightToIt(): void
    {
        $scopes = $this->scopesByViewId($this->resolver->resolveForUser($this->userSuperAdmin));

        self::assertSame(
            (int) $this->mixedUploadLicenceView->getUploadLicence()->getId(),
            $scopes[(int) $this->mixedUploadLicenceView->getId()]->getUploadLicenceId()
        );
    }

    private function reloadUser(int $id): User
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, $id);

        return $user;
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(int $id, string $email, ?ArrayCollection $licenceGroups = null, ?ArrayCollection $userToExtSystems = null, array $roles = []): User
    {
        $user = (new User())->setId($id)->setEmail($email)->setRoles($roles);
        if (null !== $licenceGroups) {
            $user->setLicenceGroups($licenceGroups);
        }
        if (null !== $userToExtSystems) {
            $user->setUserToExtSystems($userToExtSystems);
        }

        /** @var User $created */
        $created = $this->userManager->create($user);

        return $created;
    }

    /**
     * @param list<AssetLicenceGroup> $groups
     * @param list<AssetLicence> $licences
     */
    private function createView(
        string $name,
        ExtSystem $extSystem,
        array $groups,
        array $licences,
        int $position = App::ZERO,
        ?AssetLicence $uploadLicence = null,
    ): AssetListView {
        $view = (new AssetListView())
            ->setName($name)
            ->setExtSystem($extSystem)
            ->setPosition($position)
            ->setGroups(new ArrayCollection($groups))
            ->setLicences(new ArrayCollection($licences))
            ->setUploadLicence($uploadLicence)
            ->setTypes([])
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($this->author)
            ->setModifiedBy($this->author)
        ;
        $this->entityManager->persist($view);

        return $view;
    }

    /**
     * @param list<AssetLicence> $licences
     */
    private function createGroup(int $id, string $name, ExtSystem $extSystem, array $licences): AssetLicenceGroup
    {
        $group = (new AssetLicenceGroup())
            ->setId($id)
            ->setName($name)
            ->setExtSystem($extSystem)
            ->setLicences(new ArrayCollection($licences))
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
            ->setCreatedBy($this->author)
            ->setModifiedBy($this->author)
        ;
        foreach ($licences as $licence) {
            $licence->getGroups()->add($group);
        }
        $this->entityManager->persist($group);

        return $group;
    }

    /**
     * @param list<AssetListViewScope> $scopes
     *
     * @return array<int, AssetListViewScope>
     */
    private function scopesByViewId(array $scopes): array
    {
        $byId = [];
        foreach ($scopes as $scope) {
            $byId[(int) $scope->getView()->getId()] = $scope;
        }

        return $byId;
    }

    /**
     * @param list<AssetListViewScope> $scopes
     *
     * @return list<int>
     */
    private function scopeViewIds(array $scopes): array
    {
        return array_map(
            fn (AssetListViewScope $scope): int => (int) $scope->getView()->getId(),
            $scopes
        );
    }
}
