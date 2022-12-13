<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use League\Flysystem\Filesystem as BaseFilesystem;

abstract class AbstractFilesystem extends BaseFilesystem
{
    private string $key = '';

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }
}
