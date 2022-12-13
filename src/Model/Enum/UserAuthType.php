<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum UserAuthType: string implements EnumInterface
{
    use BaseEnumTrait;

    case JsonCredentials = 'json_credentials';
    case OAuth2 = 'oauth2';

    public const Default = self::JsonCredentials;
}
