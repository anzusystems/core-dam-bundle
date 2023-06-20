<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\DefaultRegionOfInterestFactory;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

final readonly class DefaultRoiProcessor
{
    public function __construct(
        private DefaultRegionOfInterestFactory $defaultRegionOfInterestFactory,
        private RegionOfInterestManager $regionOfInterestManager,
    ) {
    }

    /**
     * @param ImageFile $assetFile
     */
    public function process(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $defaultRoi = $assetFile->getRegionsOfInterest()->filter(
            fn (RegionOfInterest $roi): bool => App::ZERO === $roi->getPosition()
        )->first();

        if ($defaultRoi instanceof RegionOfInterest) {
            return $assetFile;
        }

        $roi = $this->defaultRegionOfInterestFactory->prepareDefaultRoi($assetFile);
        $assetFile->getRegionsOfInterest()->add($roi);
        $roi->setImage($assetFile);
        $this->regionOfInterestManager->create($roi, false);

        return $assetFile;
    }
}
