<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\Adapter;

use League\Flysystem\Local\LocalFilesystemAdapter as BaseLocalFilesystemAdapter;

final class LocalFileSystemAdapter extends BaseLocalFilesystemAdapter
{
    private const int DEFAULT_DIRECTORY_VISIBILITY = 0_700;

    public function ensureDirectory(string $path): void
    {
        $this->ensureDirectoryExists(
            dirname($path),
            self::DEFAULT_DIRECTORY_VISIBILITY
        );
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function appendTmpStream(string $path, $stream): void
    {
        $this->ensureDirectory($path);
        $resource = fopen($path, 'ab+');
        fwrite($resource, $stream);
        fclose($resource);
    }
}
