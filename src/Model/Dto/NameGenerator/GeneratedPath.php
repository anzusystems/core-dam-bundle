<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\NameGenerator;

final class GeneratedPath
{
    private string $dir;
    private string $fileName;
    private string $extension;
    private string $relativePath;

    public function __construct(string $dir, string $fileName, string $extension)
    {
        $this->dir = $dir;
        $this->fileName = $fileName;
        $this->extension = $extension;
        $this->relativePath = sprintf(
            '%s/%s',
            $dir,
            $fileName
        );
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    public function getFullPath(): string
    {
        if (false === empty($this->extension)) {
            return $this->relativePath . '.' . $this->extension;
        }

        return $this->relativePath;
    }
}
