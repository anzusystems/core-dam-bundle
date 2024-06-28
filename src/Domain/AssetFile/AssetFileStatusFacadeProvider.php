<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class AssetFileStatusFacadeProvider
{
    private iterable $fileStatusFacades;

    public function __construct(
        #[AutowireIterator(tag: AssetFileStatusInterface::class, indexAttribute: 'key')]
        iterable $fileStatusFacades,
    ) {
        $this->fileStatusFacades = $fileStatusFacades;
    }

    public function getStatusFacade(AssetFile $assetFile): AssetFileStatusInterface
    {
        foreach ($this->fileStatusFacades as $assetFileType => $facade) {
            if ($assetFileType === $assetFile::class) {
                return $facade;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'Missing facade for asset type (%s)',
                $assetFile::class
            )
        );
    }
}
