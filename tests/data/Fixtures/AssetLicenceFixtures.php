<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\AssetLicenceFixtures as BaseAssetLicenceFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<AssetLicence>
 */
final class AssetLicenceFixtures extends AbstractFixtures
{
    public const LICENCE_ID = BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID + 1;
    public const LICENCE_2_ID = BaseAssetLicenceFixtures::DEFAULT_LICENCE_ID + 2;

    public function __construct(
        private readonly AssetLicenceManager $assetLicenceManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetLicence::class;
    }

    public static function getDependencies(): array
    {
        return [ExtSystemFixtures::class];
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AssetLicence $assetLicence */
        foreach ($progressBar->iterate($this->getData()) as $assetLicence) {
            $assetLicence = $this->assetLicenceManager->create($assetLicence);
            $this->addToRegistry($assetLicence, (int) $assetLicence->getId());
        }
    }

    private function getData(): Generator
    {
        $blogExtSystem = $this->entityManager->find(
            ExtSystem::class,
            4
        );

        yield (new AssetLicence())
            ->setId(self::LICENCE_ID)
            ->setExtId('4')
            ->setExtSystem($blogExtSystem);

        $blogExtSystem = $this->entityManager->find(
            ExtSystem::class,
            4
        );

        yield (new AssetLicence())
            ->setId(self::LICENCE_2_ID)
            ->setExtId('5')
            ->setExtSystem($blogExtSystem);
    }
}
