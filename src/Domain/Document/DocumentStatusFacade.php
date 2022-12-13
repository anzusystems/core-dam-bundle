<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\DuplicateAssetFileException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use AnzuSystems\CoreDamBundle\Repository\DocumentFileRepository;

/**
 * @method DocumentFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class DocumentStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly DocumentFileRepository $documentFileRepository,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return DocumentFile::class;
    }

    protected function processAssetFile(AssetFile $assetFile, File $file): AssetFile
    {
        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): void
    {
        $originAsset = $this->documentFileRepository->findProcessedByChecksum($assetFile->getAssetAttributes()->getChecksum());
        if ($originAsset) {
            throw new DuplicateAssetFileException($originAsset, $assetFile);
        }
    }
}
