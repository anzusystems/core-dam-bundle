<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules;

abstract class AbstractCustomDataFactory
{
    protected const TYPE_URL = 'url';
    protected const ARG_TYPE = 'type';
    protected const ARG_VALUE = 'value';

    public function createUrl(string $url): array
    {
        return [
            self::ARG_TYPE => self::TYPE_URL,
            self::ARG_VALUE => $url,
        ];
    }

    public function getStringValue(array $data, string $key): ?string
    {
        if (isset($data[$key][self::ARG_VALUE]) && is_string($data[$key][self::ARG_VALUE])) {
            return $data[$key][self::ARG_VALUE];
        }

        return null;
    }
}
