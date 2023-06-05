<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFactory;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileManager;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Repository\AbstractAssetFileRepository;
use AnzuSystems\CoreDamBundle\Repository\DocumentFileRepository;

/**
 * @template-extends AbstractAssetFileFacade<DocumentFile>
 */
final class DocumentFacade extends AbstractAssetFileFacade
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly DocumentFactory $documentFactory,
        private readonly DocumentFileRepository $documentFileRepository,
    ) {
    }

    protected function getManager(): AssetFileManager
    {
        return $this->documentManager;
    }

    protected function getFactory(): AssetFileFactory
    {
        return $this->documentFactory;
    }

    protected function getRepository(): AbstractAssetFileRepository
    {
        return $this->documentFileRepository;
    }
}
