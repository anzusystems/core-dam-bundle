<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetFileAttributes;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\RssFeed\Item;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileCreateStrategy;

final class AudioFactory extends AssetFileFactory
{
    /**
     * @param AudioAdmCreateDto $createDto
     */
    public function createFromAdmDto(AssetLicence $licence, AssetFileAdmCreateDto $createDto): AudioFile
    {
        return $this->createBlankAudio($licence)
            ->setAssetAttributes(
                (new AssetFileAttributes())
                    ->setSize($createDto->getSize())
                    ->setChecksum($createDto->getChecksum())
                    ->setMimeType($createDto->getMimeType())
            );
    }

    public function createFromRssItem(AssetLicence $licence, Item $item): AudioFile
    {
        $audioFile = $this->createBlankAudio($licence);
        $audioFile->getAssetAttributes()
            ->setOriginUrl($item->getEnclosure()->getUrl())
            ->setCreateStrategy(AssetFileCreateStrategy::Download);

        return $audioFile;
    }
}
