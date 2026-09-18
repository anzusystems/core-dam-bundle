<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Image;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

/**
 * The file one requested item resolved to: the source itself, or the file it was taken over into.
 */
final readonly class ImageUseResolution
{
    private function __construct(
        private AssetFile $file,
        private bool $takenOver,
    ) {
    }

    public static function directUse(AssetFile $file): self
    {
        return new self($file, false);
    }

    public static function takenOver(AssetFile $file): self
    {
        return new self($file, true);
    }

    public function getFile(): AssetFile
    {
        return $this->file;
    }

    public function isTakenOver(): bool
    {
        return $this->takenOver;
    }
}
