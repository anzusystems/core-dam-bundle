<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageAdmCreateDto;

final class ImageFactory extends AssetFileFactory
{
    /**
     * @param ImageAdmCreateDto $createDto
     */
    public function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): ImageFile
    {
        return $this->createBlankImage($licence)
            ->setAssetAttributes(
                (new AssetFileAttributes())
                    ->setSize($createDto->getSize())
                    ->setChecksum($createDto->getChecksum())
                    ->setMimeType($createDto->getMimeType())
            );
    }
}
