<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentAdmCreateDto;

final class DocumentFactory extends AssetFileFactory
{
    /**
     * @param DocumentAdmCreateDto $createDto
     */
    public function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): DocumentFile
    {
        return $this->createBlankDocument($licence)
            ->setAssetAttributes(
                (new AssetFileAttributes())
                    ->setSize($createDto->getSize())
                    ->setChecksum($createDto->getChecksum())
                    ->setMimeType($createDto->getMimeType())
            );
    }
}
