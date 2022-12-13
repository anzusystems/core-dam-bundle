<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

interface FileNameGeneratorInterface
{
    public function generateFileName(?string $fileNameSuffix = null, ?string $extension = null): string;
}
