<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;
use AnzuSystems\CoreDamBundle\Repository\DocumentFileRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\VideoFileRepository;
use Doctrine\ORM\NonUniqueResultException;

class AssetFileVersionProvider
{
    public function __construct(
        private readonly ImageFileRepository $imageFileRepository,
        private readonly VideoFileRepository $videoFileRepository,
        private readonly AudioFileRepository $audioFileRepository,
        private readonly DocumentFileRepository $documentFileRepository,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getDefaultFile(Asset $asset): ?AssetFile
    {
        return match ($asset->getAttributes()->getAssetType()) {
            AssetType::Image => $this->imageFileRepository->getDefaultByAsset($asset->getId()),
            AssetType::Video => $this->videoFileRepository->getDefaultByAsset($asset->getId()),
            AssetType::Audio => $this->audioFileRepository->getDefaultByAsset($asset->getId()),
            AssetType::Document => $this->documentFileRepository->getDefaultByAsset($asset->getId()),
        };
    }
}
