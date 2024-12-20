<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;

class AssetManager extends AbstractManager
{
    public function __construct(
        private readonly AssetPropertiesRefresher $propertiesRefresher,
    ) {
    }

    public function create(Asset $asset, bool $flush = true): Asset
    {
        $this->trackCreation($asset);
        $this->entityManager->persist($asset);
        $this->flush($flush);

        return $asset;
    }

    public function update(Asset $asset, AssetAdmUpdateDto $newAssetDto, bool $flush = true): Asset
    {
        $this->trackModification($asset);
        $asset
            ->setDistributionCategory($newAssetDto->getDistributionCategory())
        ;
        $this->flush($flush);

        return $asset;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function updateExisting(Asset $asset, bool $flush = true, bool $trackModification = true): Asset
    {
        if ($trackModification) {
            $this->trackModification($asset);
        }
        $this->propertiesRefresher->refreshProperties($asset);
        $this->flush($flush);

        return $asset;
    }

    public function delete(Asset $asset, bool $flush = true): bool
    {
        $asset->setMainFile(null);
        foreach ($asset->getEpisodes() as $episode) {
            $episode->setAsset(null);
        }
        foreach ($asset->getVideoEpisodes() as $episode) {
            $episode->setAsset(null);
        }

        $asset->setEpisodes(new ArrayCollection());
        $asset->setVideoEpisodes(new ArrayCollection());
        $this->entityManager->remove($asset);
        $this->flush($flush);

        return true;
    }
}
