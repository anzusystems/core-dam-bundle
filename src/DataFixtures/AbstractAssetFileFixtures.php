<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use Doctrine\ORM\Id\AbstractIdGenerator;

abstract class AbstractAssetFileFixtures extends AbstractFixtures
{
    public const DATA_PATH = __DIR__ . '/../Resources/fixtures/';

    private AbstractIdGenerator $idGenerator;
    private int $generatorType;

    protected function getFile(LocalFilesystem $fileSystem, string $fileName): AdapterFile
    {
        $path = self::DATA_PATH . $fileName;

        return new AdapterFile(
            path: $path,
            adapterPath: $fileName,
            filesystem: $fileSystem
        );
    }

    public function configureAssignedGenerator(): void
    {
        $metadata = $this->entityManager->getClassMetadata(static::getIndexKey());
        $this->idGenerator = $metadata->idGenerator;
        $this->generatorType = $metadata->generatorType;

        parent::configureAssignedGenerator();
    }

    public function disableAssignedGenerator(): void
    {
        $metadata = $this->entityManager->getClassMetadata(static::getIndexKey());
        $metadata->setIdGenerator($this->idGenerator);
        $metadata->setIdGeneratorType($this->generatorType);
    }
}
