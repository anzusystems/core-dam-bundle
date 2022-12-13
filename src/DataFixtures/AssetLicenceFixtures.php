<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
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
    public const DEFAULT_LICENCE_ID = 1;

    public function __construct(
        private readonly AssetLicenceManager $assetLicenceManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetLicence::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        $this->configureAssignedGenerator();
        /** @var AssetLicence $assetLicence */
        foreach ($progressBar->iterate($this->getData()) as $assetLicence) {
            $assetLicence = $this->assetLicenceManager->create($assetLicence);
            $this->addToRegistry($assetLicence, (int) $assetLicence->getId());
        }
    }

    private function getData(): Generator
    {
        $cmsExtSystem = $this->entityManager->find(
            ExtSystem::class,
            1
        );

        yield (new AssetLicence())
            ->setId(self::DEFAULT_LICENCE_ID)
            ->setExtId('1')
            ->setExtSystem($cmsExtSystem);
    }
}
