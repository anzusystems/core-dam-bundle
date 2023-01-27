<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractFixtures<ImageFile>
 */
final class ImageFixtures extends AbstractAssetFileFixtures
{
    public const IMAGE_ID_1_1 = '0d584443-2718-470a-b9b1-92d2d9c7447c';
    public const IMAGE_ID_1_2 = '892d7b56-7423-4428-86a0-2d366685d823';
    public const IMAGE_ID_2 = 'd9cb26ab-81bd-4804-9f86-fb629673b1b1';
    public const IMAGE_ID_3 = '7d7456dd-80cf-4d09-9ba8-b647d8895358';
    public const IMAGE_UPLOADING_ID_4 = '7d7456dd-80cf-4d09-9ba8-b647d8895359';

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly AssetSlotFactory $assetSlotFactory,
    ) {
    }

    public static function getDependencies(): array
    {
        return [AssetLicenceFixtures::class, CustomFormElementFixtures::class];
    }

    public static function getIndexKey(): string
    {
        return ImageFile::class;
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        /** @var ImageFile $image */
        foreach ($progressBar->iterate($this->getData()) as $image) {
            $image = $this->imageManager->create($image);
            $this->addToRegistry($image, (int) $image->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);

        $file = $this->getFile($fileSystem, 'text_image_108x192.png');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_1_1
        );
        $image->getAsset()->getMetadata()->setCustomData([
            'title' => 'Custom Data Title',
            'headline' => 'Custom Data Headline',
            'description' => 'Custom Data Description',
        ]);
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'solid_image_200_100.jpeg');
        $secondImage = $this->imageFactory->createBlankAssetFile($file, $licence, self::IMAGE_ID_1_2);
        $this->assetSlotFactory->createRelation($image->getAsset(), $secondImage, 'extra');
        $secondImage->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($secondImage, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_192x108.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID),
            self::IMAGE_ID_2
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_200x200.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID),
            self::IMAGE_ID_3
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        yield $image;

        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_UPLOADING_ID_4
        );
        $image->getAssetAttributes()
            ->setMimeType('image/jpeg')
            ->setSize(6_005);
        $image->getAsset()->getMetadata()->setCustomData([
            'title' => 'Uploading',
            'description' => 'This is uploading file',
        ]);

        yield $image;
    }
}
