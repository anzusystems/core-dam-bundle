<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use League\Flysystem\Filesystem as BaseFilesystem;
use League\Flysystem\FilesystemException;

abstract class AbstractFilesystem extends BaseFilesystem
{
    private bool $fallbackEnabled = false;
    private ?AbstractFilesystem $fallbackStorage = null;

    public function read(string $location): string
    {
        $this->ensureFileFromFallback($location);

        return parent::read($location);
    }

    /**
     * @inheritdoc
     */
    public function readStream(string $location)
    {
        $this->ensureFileFromFallback($location);

        return parent::readStream($location);
    }

    public function isFallbackEnabled(): bool
    {
        return $this->fallbackEnabled;
    }

    public function setFallbackEnabled(bool $fallbackEnabled): self
    {
        $this->fallbackEnabled = $fallbackEnabled;

        return $this;
    }

    public function getFallbackStorage(): ?self
    {
        return $this->fallbackStorage;
    }

    public function setFallbackStorage(?self $fallbackStorage): self
    {
        $this->fallbackStorage = $fallbackStorage;

        return $this;
    }

    /**
     * @throws FilesystemException
     */
    protected function ensureFileFromFallback(string $location): void
    {
        if (false === $this->isFallbackEnabled()) {
            return;
        }

        if ($this->has($location)) {
            return;
        }

        if ($this->fallbackStorage && $this->fallbackStorage->has($location)) {
            $this->writeStream(
                $location,
                $this->fallbackStorage->readStream($location)
            );
        }
    }
}
