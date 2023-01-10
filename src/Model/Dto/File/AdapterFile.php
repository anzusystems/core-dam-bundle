<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\File;

use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use Symfony\Component\HttpFoundation\File\File as BaseFile;

final class AdapterFile extends BaseFile
{
    private readonly string $adapterPath;
    private readonly LocalFilesystem $localFilesystem;

    // @todo  remove $path and generate it from filesystem immediate
    public function __construct(string $path, string $adapterPath, LocalFilesystem $filesystem, bool $checkPath = true)
    {
        $this->adapterPath = $adapterPath;
        $this->localFilesystem = $filesystem;
        parent::__construct($path, $checkPath);
    }

    public static function createFromBaseFile(BaseFile $file, LocalFilesystem $filesystem): self
    {
        return new self(
            path: $filesystem->extendPath($file->getFilename()),
            adapterPath: $file->getFilename(),
            filesystem: $filesystem,
        );
    }

    public function getLocalFilesystem(): LocalFilesystem
    {
        return $this->localFilesystem;
    }

    /**
     * @return resource
     */
    public function readStream()
    {
        return $this->localFilesystem->readStream($this->getAdapterPath());
    }

    public function getAdapterPath(): string
    {
        return $this->adapterPath;
    }
}
