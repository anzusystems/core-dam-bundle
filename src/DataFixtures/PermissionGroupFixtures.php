<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\Domain\PermissionGroup\PermissionGroupManager;
use AnzuSystems\Contracts\Security\Grant;
use AnzuSystems\CoreDamBundle\Entity\PermissionGroup;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<PermissionGroup>
 */
final class PermissionGroupFixtures extends AbstractFixtures
{
    public const BASIC_GROUP_TITLE = 'DAM Basic';

    public function __construct(
        private readonly PermissionGroupManager $permissionGroupManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return PermissionGroup::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        foreach ($progressBar->iterate($this->getData()) as $permissionGroup) {
            $permissionGroup = $this->permissionGroupManager->create($permissionGroup, false);
            $this->addToRegistry($permissionGroup, $permissionGroup->getTitle());
        }
        $this->permissionGroupManager->flush();
    }

    /**
     * @return iterable<int, PermissionGroup>
     */
    private function getData(): iterable
    {
        $permissionGroup = new PermissionGroup();
        $permissionGroup
            ->setTitle(self::BASIC_GROUP_TITLE)
            ->setDescription('Basic permission group for DAM access.')
            ->setPermissions(DamPermissions::default(Grant::ALLOW))
        ;

        yield $permissionGroup;
    }
}
