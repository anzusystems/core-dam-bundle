<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

abstract class AbstractAssetFileFixtures extends AbstractFixtures
{
    public const DATA_PATH = __DIR__ . '/../Resources/fixtures/';

    protected function getFile(LocalFilesystem $fileSystem, string $fileName): AdapterFile
    {
        $path = static::DATA_PATH . $fileName;

        return new AdapterFile(
            path: $path,
            adapterPath: $fileName,
            filesystem: $fileSystem
        );
    }
}
