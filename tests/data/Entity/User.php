<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Entity;

use AnzuSystems\CoreDamBundle\Entity\DamUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends DamUser
{
    public const int ID_ADMIN = 1;
    public const int ID_ANONYMOUS = 2;
    public const int ID_CONSOLE = 3;
    public const int ID_BLOG_USER = 4;
    public const int ID_CMS_USER = 5;

    public function __construct()
    {
        parent::__construct();
        $this->setEnabled(false);
    }
}
