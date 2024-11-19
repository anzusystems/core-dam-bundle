<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\Interfaces\FileSystemStorableInterface;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use League\Flysystem\FilesystemException;

class FileStash
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $fileDeleteStash = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $backup = [];

    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    public function add(FileSystemStorableInterface $storable): void
    {
        $key = $this->fileSystemProvider->getStorageNameByStorable($storable);

        if (false === isset($this->fileDeleteStash[$key])) {
            $this->fileDeleteStash[$key] = [];
        }

        $this->fileDeleteStash[$key][] = $storable->getFilePath();
    }

    /**
     * @throws FilesystemException
     */
    public function emptyAll(): void
    {
        foreach ($this->fileDeleteStash as $storageKey => $files) {
            $storage = $this->fileSystemProvider->getFileSystemByStorageName($storageKey);
            foreach ($files as $file) {
                $storage?->delete($file);
            }
        }
    }
}
