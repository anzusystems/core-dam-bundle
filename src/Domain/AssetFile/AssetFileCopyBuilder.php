<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Domain\Image\ImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Exiftool\Exiftool;
use AnzuSystems\CoreDamBundle\FileSystem\AbstractFilesystem;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\MimeGuesser;
use AnzuSystems\CoreDamBundle\Image\VispImageManipulator;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\ImageMimeTypes;
use AnzuSystems\CoreDamBundle\Model\ValueObject\OriginStorage;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

final readonly class AssetFileCopyBuilder
{
    public function __construct(
    ) {
    }

//    /**
//     * @throws FilesystemException
//     */
//    public function copy(AssetFile $assetFile): AdapterFile
//    {
//
//    }
}
