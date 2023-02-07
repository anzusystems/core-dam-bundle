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

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var User $user */
        foreach ($progressBar->iterate($this->getData()) as $user) {
            $this->userManager->create($user);
        }
    }

    private function getData(): Generator
    {
        yield (new User())
            ->setId(User::ID_ADMIN)
            ->setEmail('admin@anzusystems.sk')
            ->setRoles([User::ROLE_ADMIN])
        ;
        yield (new User())
            ->setEmail('console@anzusystems.sk')
            ->setId(User::ID_CONSOLE);
        yield (new User())
            ->setEmail('anonymous@anzusystems.sk')
            ->setId(User::ID_ANONYMOUS);
    }
}
