<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\DependencyInjection;

use Closure;
use RuntimeException;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

final class ByteSizeEnvVarProcessor implements EnvVarProcessorInterface
{
    private const string KB = 'KB';
    private const string MB = 'MB';
    private const string GB = 'GB';
    private const string TB = 'TB';
    private const string PB = 'PB';

    private const array MULTIPLE_MAP = [
        self::KB => 1,
        self::MB => 2,
        self::GB => 3,
        self::TB => 4,
        self::PB => 5,
    ];

    private const int KILOBYTE_SIZE = 1_024;

    public function getEnv(string $prefix, string $name, Closure $getEnv): int
    {
        return $this->convertToBytes($getEnv($name));
    }

    public static function getProvidedTypes(): array
    {
        return [
            'byte_size' => 'int',
        ];
    }

    private function convertToBytes(string $byteValue): int
    {
        if (is_numeric($byteValue)) {
            return (int) $byteValue;
        }

        $value = (int) substr($byteValue, 0, -2);
        $unit = strtoupper(substr($byteValue, -2));

        if (isset(self::MULTIPLE_MAP[$unit])) {
            return $value * (self::KILOBYTE_SIZE ** self::MULTIPLE_MAP[$unit]);
        }

        throw new RuntimeException(sprintf(
            'Invalid byte size. Valid suffixes are (%s)',
            implode(', ', array_keys(self::MULTIPLE_MAP))
        ));
    }
}
