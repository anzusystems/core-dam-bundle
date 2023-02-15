<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect;

use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategorySelectRepository;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;

final class DistributionCategorySelectSynchronizer
{
    use OutputUtilTrait;

    public function __construct(
        private readonly DistributionCategorySelectManager $manager,
        private readonly DistributionCategorySelectRepository $repository,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly ExtSystemRepository $extSystemRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function synchronizeForAllExtSystems(): void
    {
        $extSystemSlugs = $this->extSystemConfigurationProvider->getExtSystemSlugs();

        foreach ($extSystemSlugs as $extSystemSlug) {
            $extSystem = $this->extSystemRepository->findOneBySlug($extSystemSlug);
            if ($extSystem instanceof ExtSystem) {
                $this->synchronizeForExtSystem($extSystem);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function synchronizeForExtSystem(ExtSystem $extSystem): void
    {
        $distributionConfig = $this->extSystemConfigurationProvider->getExtSystemConfiguration($extSystem->getSlug());

        foreach (AssetType::cases() as $type) {
            /** @var ArrayCollection<int, DistributionCategorySelect> $handledCategorySelects */
            $handledCategorySelects = new ArrayCollection();
            $distributionRequirements = $distributionConfig
                ->getByAssetType($type)
                ->getDistribution()
                ->getDistributionRequirements();
            foreach ($distributionRequirements as $serviceName => $configuration) {
                if ($configuration->getCategorySelectConfiguration()->isEnabled()) {
                    $handledCategorySelects->add(
                        $this->createIfNotExistsCategorySelect($serviceName, $extSystem, $type)
                    );
                }
            }

            $deletedCategoriesCount = $this->deleteUnhandledCategorySelects($extSystem, $type, $handledCategorySelects);

            $this->outputUtil->writeln(sprintf(
                '[ExtSystem - %s] [Distribution %s] Created or skipped %d and deleted %d categories.',
                $extSystem->getSlug(),
                $type->toString(),
                $handledCategorySelects->count(),
                $deletedCategoriesCount,
            ));
        }

        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @throws Exception
     */
    private function createIfNotExistsCategorySelect(
        string $serviceName,
        ExtSystem $extSystem,
        AssetType $type,
    ): DistributionCategorySelect {
        $existingSelect = $this->repository->findOneForExtSystemService($serviceName, $extSystem, $type);
        if ($existingSelect instanceof DistributionCategorySelect) {
            return $existingSelect;
        }

        return $this->manager->createForExtSystemService($serviceName, $extSystem, $type, flush: false);
    }

    /**
     * @param ArrayCollection<int, DistributionCategorySelect> $handledCategorySelects
     */
    private function deleteUnhandledCategorySelects(
        ExtSystem $extSystem,
        AssetType $type,
        ArrayCollection $handledCategorySelects,
    ): int {
        $deletedCount = 0;
        /** @var list<string> $notIds */
        CollectionHelper::traversableToIds($handledCategorySelects);
        foreach ($this->repository->getAllForExtSystemAndType($extSystem, $type) as $categorySelect) {
            if ($handledCategorySelects->contains($categorySelect)) {
                continue;
            }
            $this->manager->delete($categorySelect, flush: false);
            ++$deletedCount;
        }

        return $deletedCount;
    }
}
