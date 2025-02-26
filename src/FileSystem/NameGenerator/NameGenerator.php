<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Dto\NameGenerator\GeneratedPath;

readonly class NameGenerator
{
    public function __construct(
        private FileNameGeneratorInterface $fileNameGenerator,
        private DirectoryNamGeneratorInterface $directoryNameGenerator,
    ) {
    }

    public function alternatePath(
        string $originPath,
        ?string $fileNameSuffix = null,
        ?string $extension = null,
        bool $removeOldSuffix = false,
    ): GeneratedPath {
        $pathParts = pathinfo($originPath);
        $useExtension = $extension ?? (string) ($pathParts['extension'] ?? '');
        $originFileName = $pathParts['filename'];

        if ($removeOldSuffix) {
            $originFileName = explode('_', $originFileName)[App::ZERO];
        }

        $fileName = $fileNameSuffix
            ? $originFileName . '_' . $fileNameSuffix . '.' . $useExtension
            : $originFileName . '.' . $useExtension;

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
            dir: $pathParts['dirname'] ?? '',
            fileName: $pathParts['filename'] ?? '',
            extension: $pathParts['extension'] ?? '',
        );
    }

    public function generatePath(?string $extension = null, bool $dateDirPath = false, ?string $fileNameSuffix = null): GeneratedPath
    {
        return new GeneratedPath(
            dir: $this->directoryNameGenerator->generateDirectoryPath($dateDirPath ? App::getAppDate() : null),
            fileName: $this->fileNameGenerator->generateFileName(
                fileNameSuffix: $fileNameSuffix,
                extension: $extension
            ),
            extension: (string) $extension
        );
    }
}
