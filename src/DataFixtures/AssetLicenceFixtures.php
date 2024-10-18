<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<AssetLicence>
 */
final class AssetLicenceFixtures extends AbstractFixtures
{
    public const int DEFAULT_LICENCE_ID = 100_000;
    public const int SECONDARY_LICENCE_ID = 100_001;

    public function __construct(
        private readonly AssetLicenceManager $assetLicenceManager,
        private readonly AssetLicenceRepository $assetLicenceRepository,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetLicence::class;
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
        /** @var ExtSystem $cmsExtSystem */
        $cmsExtSystem = $this->entityManager->find(
            ExtSystem::class,
            1
        );

        $existingLicence = $this->assetLicenceRepository->find(self::DEFAULT_LICENCE_ID);
        if (null === $existingLicence) {
            yield (new AssetLicence())
                ->setId(self::DEFAULT_LICENCE_ID)
                ->setExtId('1')
                ->setExtSystem($cmsExtSystem);
        }

        $existingLicence = $this->assetLicenceRepository->find(self::SECONDARY_LICENCE_ID);
        if (null === $existingLicence) {
            yield (new AssetLicence())
                ->setId(self::SECONDARY_LICENCE_ID)
                ->setExtId('2')
                ->setExtSystem($cmsExtSystem);
        }
    }
}
