<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<User>
 */
final class UserFixtures extends AbstractFixtures
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return User::class;
    }

    public static function getPriority(): int
    {
        return ExtSystemFixtures::getPriority() + 1;
    }

    public function load(ProgressBar $progressBar): void
    {
        $this->configureAssignedGenerator();
        /** @var User $user */
        foreach ($progressBar->iterate($this->getData()) as $user) {
            $this->userManager->create($user);
        }
    }

    private function getData(): Generator
    {
        yield (new User())
            ->setId(User::ID_ADMIN)
            ->setRoles([User::ROLE_ADMIN])
        ;
        yield (new User())
            ->setId(User::ID_CONSOLE);
        yield (new User())
            ->setId(User::ID_ANONYMOUS);
    }
}
