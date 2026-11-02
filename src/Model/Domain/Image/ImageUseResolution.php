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

    /**
     * The file the caller asked for: a conflict is reported under it, not under the take-over copy the
     * caller has never seen.
     */
    public function getRequestedFile(): AssetFile
    {
        return $this->requestedFile;
    }

    public function isTakenOver(): bool
    {
        return false === $this->file->is($this->requestedFile);
    }
}
