<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem\NameGenerator;

use AnzuSystems\CoreDamBundle\App;
use DateTimeImmutable;
use Exception;

class DirectoryNameGenerator implements DirectoryNamGeneratorInterface
{
    private const LEVEL_INT_LEN = 19;
    private const RANDOM_BYTES_LEN = 10;

    /**
     * @throws Exception
     */
    public function generateDirectoryPath(?DateTimeImmutable $dateTime = null): string
    {
        $randomHash = App::generateSecret(self::RANDOM_BYTES_LEN * 2);

        $firstDir = $dateTime ? $dateTime->format('Y') : (string) random_int(0, self::LEVEL_INT_LEN);
        $secondDir = $dateTime ? $dateTime->format('m') : (string) random_int(0, self::LEVEL_INT_LEN);
        $thirdDir = $dateTime ? $dateTime->format('d') : (string) random_int(0, self::LEVEL_INT_LEN);

        return sprintf(
            '%s/%s/%s/%s/%s',
            $firstDir,
            $secondDir,
            $thirdDir,
            substr($randomHash, 0, self::RANDOM_BYTES_LEN),
            substr($randomHash, self::RANDOM_BYTES_LEN, strlen($randomHash)),
        );
    }
}
