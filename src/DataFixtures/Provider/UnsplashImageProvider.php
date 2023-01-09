<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures\Provider;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory\UrlFileFactory;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\RequestedUnsplashImage;
use League\Flysystem\FilesystemException;

final class UnsplashImageProvider
{
    use OutputUtilTrait;
    private const URL_TEMPLATE = 'https://source.unsplash.com/featured/%dx%d?%s';

    public function __construct(
        private readonly UrlFileFactory $urlFileFactory,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws FilesystemException
     */
    public function downloadImage(RequestedUnsplashImage $image): void
    {
        $file = $this->urlFileFactory->downloadFile(
            $this->getBaseUrl($image)
        );

        $fixturesPath = $this->nameGenerator->alternatePath(
            originPath: $file->getAdapterPath(),
            extension: FileHelper::guessExtension($file->getMimeType())
        );

        $this->fileSystemProvider->getFixturesFileSystem()
            ->writeStream(
                $fixturesPath->getFileName(),
                $this->fileSystemProvider->getTmpFileSystem()->readStream(
                    $file->getAdapterPath()
                )
            );
    }

    /**
     * @throws FilesystemException
     * @throws AssetFileProcessFailed
     */
    public function downloadImages(int $count, array $keyWords = [], array $sizeList = []): void
    {
        $progress = $this->outputUtil->createProgressBar($count);
        $fileSystem = $this->fileSystemProvider->getFixturesFileSystem();

        $this->outputUtil->writeln(sprintf('Writing fixture images to directory %s', $fileSystem->extendPath('')));
        $progress->start();

        for ($i = 0; $i < $count; $i++) {
            $keyWord = $keyWords[array_rand($keyWords)];
            $size = $sizeList[array_rand($sizeList)];

            $this->downloadImage(new RequestedUnsplashImage($size[0], $size[1], $keyWord));

            $progress->advance();
        }

        $progress->finish();
        $this->outputUtil->writeln('');
    }

    private function getBaseUrl(RequestedUnsplashImage $image): string
    {
        return sprintf(
            self::URL_TEMPLATE,
            $image->getWidth(),
            $image->getHeight(),
            $image->getKeyword(),
        );
    }
}
