<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\Video;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\DataFixtures\VideoFixtures;
use AnzuSystems\CoreDamBundle\Domain\ImagePreview\ImagePreviewFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFactory;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;

final class VideoStatusFacadeTest extends CoreDamKernelTestCase
{
    private const string FILE_NAME = 'video_example.mp4';
    private const int LICENCE_ID = 100_000;

    public function testProcessKeepsPresetImagePreview(): void
    {
        $imageFileRepository = $this->getService(ImageFileRepository::class);
        $imageFile = $imageFileRepository->find(ImageFixtures::IMAGE_ID_1_1);
        self::assertInstanceOf(ImageFile::class, $imageFile);

        $file = new AdapterFile(
            path: VideoFixtures::DATA_PATH . self::FILE_NAME,
            adapterPath: self::FILE_NAME,
            filesystem: $this->getService(FileSystemProvider::class)->createLocalFilesystem(VideoFixtures::DATA_PATH),
        );
        $licence = $this->getService(AssetLicenceRepository::class)->find(self::LICENCE_ID);
        $videoFile = $this->getService(VideoFactory::class)->createFromFile($file, $licence);
        self::assertInstanceOf(VideoFile::class, $videoFile);
        $imagePreview = $this->getService(ImagePreviewFactory::class)->createFromImageFile($imageFile, flush: false);
        $videoFile->setImagePreview($imagePreview);
        $videoFile->getAssetAttributes()->setStatus(AssetFileProcessStatus::Uploaded);
        $imageFilesCount = $imageFileRepository->count([]);

        $this->getService(VideoStatusFacade::class)->storeAndProcess($videoFile, $file);

        self::assertTrue($videoFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed));
        self::assertSame($imagePreview, $videoFile->getImagePreview());
        self::assertSame(ImageFixtures::IMAGE_ID_1_1, $videoFile->getImagePreview()->getImageFile()->getId());
        self::assertSame($imageFilesCount, $imageFileRepository->count([]));
    }
}
