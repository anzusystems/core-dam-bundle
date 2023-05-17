<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\FileSystem\NameGenerator\NameGenerator;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\File;

final class TmpLocalFilesystem extends LocalFilesystem
{
    /**
     * @var array<int, string>
     */
    private array $paths = [];
    private NameGenerator $nameGenerator;

    /**
     * @throws FilesystemException
     */
    public function clearPaths(): void
    {
        foreach ($this->paths as $path) {
            if ($this->fileExists($path)) {
                //                $this->delete($path);
            }
        }
        $this->paths = [];
    }

    public function getTmpFileName(?string $extension = null): string
    {
        $path = $this->nameGenerator->generatePath($extension)->getFileName();
        $this->paths[] = $path;

        return $path;
    }

    /**
     * @throws FilesystemException
     */
    public function writeTmpFileFromFilesystem(Filesystem $filesystem, string $filePath): File
    {
        return new File(
            $this->extendPath($this->writeTmpFile($filesystem->readStream($filePath)))
        );
    }

    /**
     * @param resource $resource
     *
     * @throws FilesystemException
     */
    public function writeTmpFileFromStream($resource): File
    {
        return new File(
            $this->extendPath($this->writeTmpFile($resource))
        );
    }

    /**
     * @param resource $stream
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function writeTmpFile($stream): string
    {
        $path = $this->getTmpFileName();

        $this->writeStream($path, $stream);

        return $path;
    }

    public function setNameGenerator(NameGenerator $nameGenerator): self
    {
        $this->nameGenerator = $nameGenerator;

        return $this;
    }
}
