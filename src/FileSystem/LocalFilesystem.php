<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\FileSystem\Adapter\LocalFileSystemAdapter;

class LocalFilesystem extends AbstractFilesystem
{
    public function __construct(
        private readonly LocalFileSystemAdapter $adapter,
        private readonly string $directory,
    ) {
        parent::__construct($adapter);
    }

    public function ensureDirectory(string $path): void
    {
        $this->adapter->ensureDirectory($this->extendPath($path));
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function appendTmpStream(string $path, $stream): void
    {
        $this->adapter->appendTmpStream($this->extendPath($path), $stream);
    }

    public function extendPath(string $path): string
    {
        return $this->directory . '/' . $path;
    }
}
