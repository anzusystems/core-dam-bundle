<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormFactory;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormManager;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategory\DistributionCategoryManager;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use AnzuSystems\CoreDamBundle\Entity\Embeds\CustomFormElementAttributes;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\Enum\CustomFormElementType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<DistributionCategory>
 */
final class DistributionCategoryFixtures extends AbstractFixtures
{
    public const CATEGORY_1 = '4f8ad6b7-46d6-41a4-98e8-b424aa6b8e37';
    public const CATEGORY_2 = 'c507dc39-fcb5-42c8-909e-f5d110627716';
    public const CATEGORY_3 = '154358fc-3239-44a1-8f47-dbc65da7285e';
    public const CATEGORY_4 = '134c6a04-dc5e-4ca8-8e3f-29bfd5329b7a';

    public function __construct(
        private readonly DistributionCategoryManager $distributionCategoryManager,
    ) {
    }

    public function getEnvironments(): array
    {
        return ['test'];
    }

    public static function getIndexKey(): string
    {
        return DistributionCategory::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var DistributionCategory $category */
        foreach ($progressBar->iterate($this->getData()) as $category) {
            $this->distributionCategoryManager->create($category);
        }
    }

    /**
     * @return Generator<int, DistributionCategory>
     */
    private function getData(): Generator
    {
        $cmsExtSystem = $this->entityManager->find(ExtSystem::class, 1);
        $blogExtSystem = $this->entityManager->find(ExtSystem::class, 4);

        yield (new DistributionCategory())
            ->setId(self::CATEGORY_1)
            ->setType(AssetType::Audio)
            ->setName('Audio category 1')
            ->setExtSystem($cmsExtSystem);

        yield (new DistributionCategory())
            ->setId(self::CATEGORY_2)
            ->setType(AssetType::Video)
            ->setName('Vide category 1')
            ->setExtSystem($cmsExtSystem);

        yield (new DistributionCategory())
            ->setId(self::CATEGORY_3)
            ->setType(AssetType::Video)
            ->setName('Vide category 2')
            ->setExtSystem($cmsExtSystem);

        yield (new DistributionCategory())
            ->setId(self::CATEGORY_4)
            ->setType(AssetType::Video)
            ->setName('Vide category 3 (blog system)')
            ->setExtSystem($blogExtSystem);
    }
}
