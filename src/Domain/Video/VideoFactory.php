<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Video;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmCreateDto;

/**
 * @extends AbstractAssetFileFactory<VideoFile>
 */
final class VideoFactory extends AbstractAssetFileFactory
{
    /**
     * @param VideoAdmCreateDto $createDto
     */
    public function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): VideoFile
    {
        return $this->createBlankVideo($licence)
            ->setAssetAttributes(
                (new AssetFileAttributes())
                    ->setSize($createDto->getSize())
                    ->setChecksum($createDto->getChecksum())
                    ->setMimeType($createDto->getMimeType())
            );
    }
}
