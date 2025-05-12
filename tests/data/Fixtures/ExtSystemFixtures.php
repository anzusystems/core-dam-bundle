<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemManager;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<ExtSystem>
 */
final class ExtSystemFixtures extends AbstractFixtures
{
    public const ID_CMS = 1;
    public const ID_BLOG = 4;

    public function __construct(
        private readonly ExtSystemManager $extSystemManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return ExtSystem::class;
    }

    public static function getPriority(): int
    {
        return AssetLicenceFixtures::getPriority() + 1;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var ExtSystem $extSystem */
        foreach ($progressBar->iterate($this->getData()) as $extSystem) {
            $this->extSystemManager->create($extSystem, false);
        }
        $this->extSystemManager->flush();
    }

    /**
     * @return Generator<int, ExtSystem>
     */
    private function getData(): Generator
    {
        yield (new ExtSystem())
            ->setId(self::ID_CMS)
            ->setName('CMS system')
            ->setSlug('cms')
        ;
        yield (new ExtSystem())
            ->setId(self::ID_BLOG)
            ->setName('Blog system')
            ->setSlug('blog')
        ;
    }
}
