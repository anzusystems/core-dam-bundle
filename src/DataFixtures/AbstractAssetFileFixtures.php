<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;

abstract class AbstractAssetFileFixtures extends AbstractFixtures
{
    public const DATA_PATH = __DIR__ . '/../Resources/fixtures/';

    protected function getFile(LocalFilesystem $fileSystem, string $fileName): File
    {
        $path = self::DATA_PATH . $fileName;

        return new File(
            path: $path,
            adapterPath: $fileName,
            filesystem: $fileSystem
        );
    }
}
