<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Domain\Image;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;

final readonly class ImageUseResolution
{
    private function __construct(
        private AssetFile $file,
        private AssetFile $requestedFile,
    ) {
    }

    public static function directUse(AssetFile $file): self
    {
        return new self($file, $file);
    }

    public static function takenOver(AssetFile $file, AssetFile $requestedFile): self
    {
        return new self($file, $requestedFile);
    }

    public function getFile(): AssetFile
    {
        return $this->file;
    }

    public function isTakenOver(): bool
    {
        return false === $this->file->is($this->requestedFile);
    }
}
