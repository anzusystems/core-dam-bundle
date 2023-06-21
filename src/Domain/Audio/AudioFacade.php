<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Audio;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\AudioFileRepository;

/**
 * @template-extends AbstractAssetFileFacade<AudioFile>
 */
final class AudioFacade extends AbstractAssetFileFacade
{
    public function __construct(
        private readonly AudioManager $audioManager,
        private readonly AudioFactory $audioFactory,
        private readonly AudioFileRepository $audioFileRepository,
    ) {
    }

    protected function getManager(): AssetFileManager
    {
        return $this->audioManager;
    }

    protected function getFactory(): AbstractAssetFileFactory
    {
        return $this->audioFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->audioFileRepository;
    }
}
