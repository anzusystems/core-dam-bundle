<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysCreateDto;

final readonly class AssetSysFactory
{
    public function __construct(
        private AssetFileFactory $assetFileFactory,
    ) {
    }

    public function createFromDto(AssetFileSysCreateDto $dto): AssetFile
    {
        // todo change source storage name (next ext system implementation)
        return $this->assetFileFactory->createAssetFileForStorage(
            storageName: 'ext.scraper',
            filePath: $dto->getPath(),
            licence: $dto->getLicence()
        );
    }
}
