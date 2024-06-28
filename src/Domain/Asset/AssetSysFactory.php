<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;

final readonly class AssetSysFactory
{
    public function __construct(
        private AssetFileFactory $assetFileFactory,
        private ExtSystemConfigurationProvider $configurationProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws NonUniqueResultException
     * @throws InvalidMimeTypeException
     */
    public function createFromDto(AssetFileSysPathCreateDto $dto): AssetFile
    {
        $configuration = $this->configurationProvider->getExtSystemConfiguration($dto->getExtSystem()->getSlug());

        return $this->assetFileFactory->createAssetFileForStorage(
            storageName: $configuration->getExtStorage(),
            filePath: $dto->getPath(),
            licence: $dto->getLicence()
        );
    }
}
