<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileStatusFacadeProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFactory;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageManager;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Author;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\Keyword;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\AuthorRepository;
use AnzuSystems\CoreDamBundle\Repository\KeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @extends AbstractAssetFileFixtures<ImageFile>
 */
final class ImageFixtures extends AbstractAssetFileFixtures
{
    public const string IMAGE_ID_1_1 = '0d584443-2718-470a-b9b1-92d2d9c7447c';
    public const string IMAGE_ID_1_2 = '892d7b56-7423-4428-86a0-2d366685d823';
    public const string IMAGE_ID_2 = 'd9cb26ab-81bd-4804-9f86-fb629673b1b1';
    public const string IMAGE_ID_3 = '7d7456dd-80cf-4d09-9ba8-b647d8895358';
    public const string IMAGE_UPLOADING_ID_4 = '7d7456dd-80cf-4d09-9ba8-b647d8895359';

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly AssetLicenceRepository $licenceRepository,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileStatusFacadeProvider $facadeProvider,
        private readonly AuthorRepository $authorRepository,
        private readonly KeywordRepository $keywordRepository,
    ) {
    }

    public static function getDependencies(): array
    {
        return [AssetLicenceFixtures::class, AuthorFixtures::class, KeywordFixtures::class];
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
            $this->addToRegistry($image, (string) $image->getId());
        }
    }

    private function getData(): Generator
    {
        $fileSystem = $this->fileSystemProvider->createLocalFilesystem(self::DATA_PATH);
        /** @var AssetLicence $licence */
        $licence = $this->licenceRepository->find(AssetLicenceFixtures::DEFAULT_LICENCE_ID);
        /** @var Keyword $keyword */
        $keyword = $this->keywordRepository->find(KeywordFixtures::KEYWORD_1);
        /** @var Author $author */
        $author = $this->authorRepository->find(AuthorFixtures::AUTHOR_1);

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
        $image->getFlags()->setSingleUse(true);
        $image->getAsset()->setAuthors(new ArrayCollection([$author]));
        $image->getAsset()->setKeywords(new ArrayCollection([$keyword]));

        yield $image;

        $file = $this->getFile($fileSystem, 'solid_image_200_100.jpeg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_1_2
        );
        $image->getAsset()->getMetadata()->setCustomData([
            'title' => 'Image 1_2 title',
        ]);
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_192x108.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
            self::IMAGE_ID_2
        );
        $image->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $this->facadeProvider->getStatusFacade($image)->storeAndProcess($image, $file);
        $image->getAsset()->getAssetFlags()->setDescribed(true);

        yield $image;

        $file = $this->getFile($fileSystem, 'text_image_200x200.jpg');
        $image = $this->imageFactory->createFromFile(
            $file,
            $licence,
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
