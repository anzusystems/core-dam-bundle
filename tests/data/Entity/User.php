<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Entity;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\DamUser;
use AnzuSystems\CoreDamBundle\Entity\PermissionGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends DamUser
{
    public const int ID_ADMIN = 1;
    public const int ID_ANONYMOUS = 2;
    public const int ID_CONSOLE = 3;
    public const int ID_BLOG_USER = 4;
    public const int ID_CMS_USER = 5;

    #[ORM\ManyToMany(targetEntity: PermissionGroup::class, inversedBy: 'users', fetch: App::DOCTRINE_EXTRA_LAZY, indexBy: 'id')]
    #[ORM\JoinTable(name: 'user_permission_group')]
    protected Collection $permissionGroups;

    public function __construct()
    {
        parent::__construct();
        $this->permissionGroups = new ArrayCollection();
        $this->setEnabled(false);
    }
}
