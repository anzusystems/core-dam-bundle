<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;

/**
 * @template T of object
 * @extends AbstractFixtures<T>
 */
abstract class AbstractAssetFileFixtures extends AbstractFixtures
{
    public const string DATA_PATH = __DIR__ . '/../Resources/fixtures/';

    public function getEnvironments(): array
    {
        return ['dev', 'test'];
    }

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
