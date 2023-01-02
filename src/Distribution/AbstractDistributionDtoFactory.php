<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategoryOption;
use AnzuSystems\CoreDamBundle\Repository\DistributionCategorySelectRepository;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractDistributionDtoFactory
{
    private readonly DistributionCategorySelectRepository $categorySelectRepository;

    #[Required]
    public function setCategorySelectRepository(DistributionCategorySelectRepository $categorySelectRepository): void
    {
        $this->categorySelectRepository = $categorySelectRepository;
    }

    public function getSelectedOption(AssetFile $assetFile, Distribution $distribution): ?DistributionCategoryOption
    {
        $asset = $assetFile->getAsset();

        $select = $this->categorySelectRepository->findOneForExtSystemService(
            $distribution->getDistributionService(),
            $assetFile->getExtSystem(),
            $assetFile->getAssetType(),
        );

        if (null === $select || null === $asset->getDistributionCategory()) {
            return null;
        }

        $selectedOption = $asset->getDistributionCategory()->getSelectedOptions()->filter(
            fn (DistributionCategoryOption $option): bool => $option->getSelect() === $select
        )->first();

        if ($selectedOption instanceof DistributionCategoryOption) {
            return $selectedOption;
        }

        return null;
    }
}
