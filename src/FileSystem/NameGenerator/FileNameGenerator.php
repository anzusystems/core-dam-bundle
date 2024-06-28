<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

use AnzuSystems\CoreDamBundle\App;
use Exception;

class FileNameGenerator implements FileNameGeneratorInterface
{
    private const int FILENAME_LENGTH = 32;

    /**
     * @throws Exception
     */
    public function generateFileName(?string $fileNameSuffix = null, ?string $extension = null): string
    {
        $name = App::generateSecret(self::FILENAME_LENGTH);
        if (false === (null === $fileNameSuffix)) {
            $name .= '_' . $fileNameSuffix;
        }
        if ($extension) {
            $name .= '.' . $extension;
        }

        return $name;
    }
}
