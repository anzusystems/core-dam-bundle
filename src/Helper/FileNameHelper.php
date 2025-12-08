<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

final class FileNameHelper
{
    public static function getFilenameWithoutExtension(string $fileName): string
    {
        $pathParts = pathinfo($fileName);

        return $pathParts['filename'];
    }

    public static function addExtensionToFilename(string $fileName, string $newExt): string
    {
        if (empty($newExt)) {
            return $fileName;
        }

        return sprintf('%s.%s', $fileName, $newExt);
    }

    public static function changeFileExtension(string $fileName, string $newExt): string
    {
        return self::addExtensionToFilename(self::getFilenameWithoutExtension($fileName), $newExt);
    }

    public static function concatDirFile(string $dir, string $fileName, ?string $extension = null): string
    {
        $path = sprintf('%s/%s', $dir, $fileName);

        return $extension
            ? $path . '.' . $extension
            : $path;
    }
}
