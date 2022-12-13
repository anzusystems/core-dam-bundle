<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Traits;

use AnzuSystems\CoreDamBundle\Domain\AssetFile\FileStash;
use Symfony\Contracts\Service\Attribute\Required;

trait FileStashAwareTrait
{
    protected FileStash $fileStash;

    #[Required]
    public function setFileStash(FileStash $fileStash): void
    {
        $this->fileStash = $fileStash;
    }
}
