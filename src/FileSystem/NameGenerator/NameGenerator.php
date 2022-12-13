<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

use AnzuSystems\CoreDamBundle\Model\Dto\NameGenerator\GeneratedPath;

class NameGenerator
{
    public function __construct(
        private readonly FileNameGeneratorInterface $fileNameGenerator,
        private readonly DirectoryNamGeneratorInterface $directoryNameGenerator,
    ) {
    }

    public function alternatePath(string $originPath, ?string $fileNameSuffix = null, ?string $extension = null): GeneratedPath
    {
        $pathParts = pathinfo($originPath);
        $useExtension = $extension ?? (string) $pathParts['extension'];
        $fileName = $fileNameSuffix
            ? $pathParts['filename'] . '_' . $fileNameSuffix . '.' . $useExtension
            : $pathParts['filename'] . '.' . $useExtension;

        return new GeneratedPath(
            dir: $pathParts['dirname'],
            fileName: $fileName,
            extension: $useExtension
        );
    }

    public function getPath(string $path): GeneratedPath
    {
        $pathParts = pathinfo($path);

        return new GeneratedPath(
            dir: $pathParts['dirname'],
            fileName: $pathParts['filename'],
            extension: $pathParts['extension']
        );
    }

    public function generatePath(?string $extension = null): GeneratedPath
    {
        return new GeneratedPath(
            dir: $this->directoryNameGenerator->generateDirectoryPath(),
            fileName: $this->fileNameGenerator->generateFileName(
                extension: $extension
            ),
            extension: (string) $extension
        );
    }
}
