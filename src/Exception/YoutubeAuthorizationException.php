<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use RuntimeException as BaseRuntimeException;

class YoutubeAuthorizationException extends BaseRuntimeException
{
    public const MISSING_SCOPE_MESSAGE = 'missing_scope';
    public const MISSING_REFRESH_TOKEN_MESSAGE = 'missing_refresh_token';
    public const MISSING_ACCESS_TOKEN_MESSAGE = 'missing_access_token';
    public const NOT_AUTHORIZED_MESSAGE = 'not_authorized_message';
    public const INVALID_EXCHANGE_TOKEN_STATE = 'invalid_exchange_token_state';
}
