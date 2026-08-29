<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\AssetListView;

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

    private AssetListViewResolver $resolver;
    private UserManager $userManager;
    private User $userMember;
    private User $userOutsider;
    private User $userGlobalOnly;
    private AssetListView $targetedView;
    private AssetListView $globalViewA;
    private AssetListView $globalViewB;
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

        $this->userMember = $this->createUser(self::USER_MEMBER_ID, 'member@anzusystems.sk', licenceGroups: new ArrayCollection([$group100]));
        $this->userOutsider = $this->createUser(self::USER_OUTSIDER_ID, 'outsider@anzusystems.sk');
        $this->userGlobalOnly = $this->createUser(self::USER_GLOBAL_ONLY_ID, 'global-only@anzusystems.sk', userToExtSystems: new ArrayCollection([$blogExtSystem]));

        $this->targetedView = $this->createView('Targeted at group 100', $blogExtSystem, [$group100], [$licenceInGroup]);
        $this->globalViewA = $this->createView('Global - reachable licence', $blogExtSystem, [], [$licenceInGroup]);
        $this->globalViewB = $this->createView('Global - unreachable licence', $blogExtSystem, [], [$licenceOutsideGroup]);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // The resolver works on hydrated users; entities built in memory carry uninitialized collections.
        $this->userMember = $this->reloadUser(self::USER_MEMBER_ID);
        $this->userOutsider = $this->reloadUser(self::USER_OUTSIDER_ID);
        $this->userGlobalOnly = $this->reloadUser(self::USER_GLOBAL_ONLY_ID);
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

    private function reloadUser(int $id): User
    {
        /** @var User $user */
        $user = $this->entityManager->find(User::class, $id);

        return $user;
    }

    private function createUser(int $id, string $email, ?ArrayCollection $licenceGroups = null, ?ArrayCollection $userToExtSystems = null): User
    {
        $user = (new User())->setId($id)->setEmail($email);
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
    private function createView(string $name, ExtSystem $extSystem, array $groups, array $licences): AssetListView
    {
        $view = (new AssetListView())
            ->setName($name)
            ->setExtSystem($extSystem)
            ->setGroups(new ArrayCollection($groups))
            ->setLicences(new ArrayCollection($licences))
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
