<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

use DateTimeImmutable;

interface DirectoryNamGeneratorInterface
{
    public function generateDirectoryPath(?DateTimeImmutable $dateTime = null): string;
}
