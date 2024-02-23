<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup\AssetLicenceGroupManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<AssetLicenceGroup>
 */
final class AssetLicenceGroupFixtures extends AbstractFixtures
{
    public const int LICENCE_GROUP_ID = 100;

    public function __construct(
        private readonly AssetLicenceGroupManager $assetLicenceGroupManager,
    ) {
    }

    public static function getIndexKey(): string
    {
        return AssetLicenceGroup::class;
    }

    public static function getDependencies(): array
    {
        return [AssetLicenceFixtures::class];
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var AssetLicenceGroup $assetLicenceGroup */
        foreach ($progressBar->iterate($this->getData()) as $assetLicenceGroup) {
            $assetLicenceGroup = $this->assetLicenceGroupManager->create($assetLicenceGroup);
            $this->addToRegistry($assetLicenceGroup, (int) $assetLicenceGroup->getId());
        }
    }

    private function getData(): Generator
    {
        /** @var AssetLicence $licence */
        $licence = $this->entityManager->find(
            AssetLicence::class,
            AssetLicenceFixtures::LICENCE_ID
        );

        /** @var ExtSystem $blogExtSystem */
        $blogExtSystem = $this->entityManager->find(
            ExtSystem::class,
            4
        );

        yield (new AssetLicenceGroup())
            ->setId(self::LICENCE_GROUP_ID)
            ->setName('Group 100')
            ->setExtSystem($blogExtSystem)
            ->setLicences(new ArrayCollection([$licence]))
        ;
    }
}
