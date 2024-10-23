<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Fixtures;

use AnzuSystems\CoreDamBundle\DataFixtures\AbstractAssetFileFixtures;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Domain\Image\ImagePositionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<ImageFile>
 */
final class ImageFixtures extends AbstractAssetFileFixtures
{
    public const string DATA_PATH = __DIR__ . '/../../../src/Resources/fixtures/';

    public const string IMAGE_ID_1 = 'e9cb26ab-81bd-4804-9f86-fb629673b1b1';
    public const string IMAGE_ID_2 = '8d7456dd-80cf-4d09-9ba8-b647d8895358';
    public const string IMAGE_ID_2_1 = '8e7456dd-80cf-4d09-9ba8-b647d8895358';
    public const string IMAGE_ID_3 = '8e7456dd-80cf-4d09-9ba8-b647d8895359';

    public const string IMAGE_ID_5 = '7d7456dd-80cf-4d09-9ba8-a647d8895345';

    public const string IMAGE_ID_4 = '7d7456dd-80cf-4d09-9ba8-b647d8895345';

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly AssetSlotFactory $assetSlotFactory,
        private readonly ImagePositionFacade $assetFilePositionFacade,
    ) {
    }

    public static function getIndexKey(): string
    {
        return ImageFile::class;
    }

    public static function getDependencies(): array
    {
        return [AssetLicenceFixtures::class];
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
            $this->addToRegistry($image, (string) $image->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        /** @var AssetLicence $licence */
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::LICENCE_ID);

        $file = $this->getFile($fileSystem, 'text_image_192x108.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_1
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_200x200.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_2
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        yield $image;

        $file = $this->getFile($fileSystem, 'solid_image_200_100.jpeg');
        $secondImage = $this->imageFactory->createBlankAssetFile($file, $licence, self::IMAGE_ID_2_1);
        $this->assetSlotFactory->createRelation($image->getAsset(), $secondImage, 'test');
        $secondImage->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($secondImage, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $licence = $this->licenceRepository->find(AssetLicenceFixtures::LICENCE_2_ID);

        $file = $this->getFile($fileSystem, 'text_image_108x192.png');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_3
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        yield $image;

        /** @var AssetLicence $secondaryLicence */
        $secondaryLicence = $this->licenceRepository->find(AssetLicenceFixtures::FIRST_SYS_SECONDARY_LICENCE);

        $file = $this->getFile($fileSystem, 'text_image_192x108.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $secondaryLicence,
            self::IMAGE_ID_5
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $this->assetFilePositionFacade->setToSlot($image->getAsset(), $image, 'free');

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_200x200.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $secondaryLicence,
            self::IMAGE_ID_4
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);

        yield $image;
    }
}
