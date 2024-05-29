<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\User\UserManager;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<User>
 */
final class UserFixtures extends AbstractFixtures
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly AssetLicenceFixtures $assetLicenceFixtures,
        private readonly BaseAssetLicenceFixtures $baseAssetLicenceFixtures,
    ) {
    }

    public static function getIndexKey(): string
    {
        return User::class;
    }

    public static function getDependencies(): array
    {
        return [AssetLicenceFixtures::class, BaseAssetLicenceFixtures::class];
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
        $licenceBlog = $this->assetLicenceFixtures->getOneFromRegistry(AssetLicenceFixtures::LICENCE_ID);
        $licenceCms = $this->baseAssetLicenceFixtures->getOneFromRegistry(BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $userBlog = (new User())
            ->setId(User::ID_BLOG_USER)
            ->setEmail('blog_user@anzusystems.sk')
            ->setAssetLicences(new ArrayCollection([$licenceCms, $licenceBlog]))
            ->setUserToExtSystems(new ArrayCollection([$licenceCms->getExtSystem(), $licenceBlog->getExtSystem()]))
            ->setEnabled(true)
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
        ;
        $userBlog
            ->setCreatedBy($userBlog)
            ->setModifiedBy($userBlog)
        ;

        yield $userBlog;

        $userCms = (new User())
            ->setId(User::ID_CMS_USER)
            ->setEmail('cms_user@anzusystems.sk')
            ->setAssetLicences(new ArrayCollection([$licenceCms]))
            ->setUserToExtSystems(new ArrayCollection([$licenceCms->getExtSystem()]))
            ->setEnabled(true)
            ->setCreatedAt(App::getAppDate())
            ->setModifiedAt(App::getAppDate())
        ;
        $userCms
            ->setCreatedBy($userCms)
            ->setModifiedBy($userCms)
        ;

        yield $userCms;
    }
}
