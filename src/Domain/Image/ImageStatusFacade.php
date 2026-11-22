<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Image;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AbstractAssetFileStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\DefaultRoiProcessor;
use AnzuSystems\CoreDamBundle\Domain\Image\FileProcessor\OptimalCropsProcessor;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;

/**
 * @method ImageFile finishUpload(AssetAdmFinishDto $assetFinishDto, AssetFile $assetFile)
 */
final class ImageStatusFacade extends AbstractAssetFileStatusFacade
{
    public function __construct(
        private readonly OptimalCropsProcessor $optimalCropsProcessor,
        private readonly DefaultRoiProcessor $defaultRoiProcessor,
        private readonly ImageFileRepository $imageFileRepository,
        private readonly VispImageManipulator $imageManipulator,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return ImageFile::class;
    }

    /**
     * @throws ImageManipulatorException
     * @throws FilesystemException
     * @throws SerializerException
     * @throws InvalidArgumentException
     */
    protected function processAssetFile(AssetFile $assetFile, AdapterFile $file): AssetFile
    {
        $imageFile = $this->getImage($assetFile);
        $this->imageManipulator->loadFile($file->getRealPath());

        // TODo most dominant color memory problems
        $imageFile->getImageAttributes()
            ->setAnimated($this->imageManipulator->isAnimated())
//            ->setMostDominantColor($this->imageManipulator->getMostDominantColor())
        ;

        $this->optimalCropsProcessor->process($imageFile, $file);
        $this->defaultRoiProcessor->process($imageFile, $file);
        $this->imageManipulator->clean();
        $this->joinTakeOverGroup($imageFile);

        return $imageFile;
    }

    protected function checkDuplicate(AssetFile $assetFile): ?AssetFile
    {
        return $this->imageFileRepository->findProcessedByChecksumAndLicence(
            checksum: $assetFile->getAssetAttributes()->getChecksum(),
            licence: $assetFile->getLicence(),
            excludeAssetId: $assetFile->getAsset()->getId(),
        );
    }

    /**
     * A photo uploaded straight into a licence that allows direct use, while the same bytes came in through an
     * agency licence, joins the group of the agency file and takes its single use flag. Without it the upload would
     * be an independent photo and the single use rule could be bypassed. A new file has no CMS rows yet.
     */
    private function joinTakeOverGroup(ImageFile $imageFile): void
    {
        $attributes = $imageFile->getAssetAttributes();
        if ($attributes->isTakenOver() || false === $imageFile->getLicence()->getFlags()->isDirectUseAllowed()) {
            return;
        }

        $source = $this->imageFileRepository->findProcessedTakeOverSourceByChecksum(
            $attributes->getChecksum(),
            $imageFile->getLicence(),
        );
        if (null === $source) {
            return;
        }

        $attributes->setTakenOverFromId($source->getTakeOverRootId());
        if ($source->getFlags()->isSingleUse()) {
            $imageFile->getFlags()->setSingleUse(true);
        }
    }

    private function getImage(AssetFile $assetFile): ImageFile
    {
        if (false === ($assetFile instanceof ImageFile)) {
            throw new InvalidArgumentException('Asset type must be a type of image');
        }

        return $assetFile;
    }
}
