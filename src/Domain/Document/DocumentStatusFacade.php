<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Document;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
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

    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        return $assetFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): ?AssetFile
    {
        return $this->documentFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
        );
    }
}
