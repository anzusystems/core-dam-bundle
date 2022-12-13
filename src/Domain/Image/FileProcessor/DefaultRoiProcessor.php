<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\DefaultRegionOfInterestFactory;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

final class DefaultRoiProcessor
{
    public function __construct(
        private readonly DefaultRegionOfInterestFactory $defaultRegionOfInterestFactory,
        private readonly RegionOfInterestManager $regionOfInterestManager,
    ) {
    }

    /**
     * @param ImageFile $assetFile
     */
    public function process(AssetFile $assetFile, File $file): AssetFile
    {
        $roi = $this->defaultRegionOfInterestFactory->prepareDefaultRoi($assetFile);
        $assetFile->getRegionsOfInterest()->add($roi);
        $roi->setImage($assetFile);
        $this->regionOfInterestManager->create($roi, false);

        return $assetFile;
    }
}
