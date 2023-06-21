<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageAdmCreateDto;

/**
 * @extends AbstractAssetFileFactory<AssetFile>
 */
final class AssetFileFactory extends AbstractAssetFileFactory
{
    /**
     * @param ImageAdmCreateDto $createDto
     */
    public function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): AssetFile
    {
        throw new DomainException('Not implemented');
    }
}
