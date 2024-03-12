<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle;

use AnzuSystems\Contracts\AnzuApp;
use Exception;

final class App extends AnzuApp
{
    public const int ZERO = 0;
    public const string DOCTRINE_EXTRA_LAZY = 'EXTRA_LAZY';
    public const string CACHE_STRATEGY = 'NONSTRICT_READ_WRITE';
    public const string DATE_TIME_API_FORMAT = 'Y-m-d\TH:i:s.u\Z';
    public const string ORDER_ASC = 'ASC';

    public const string ENTITY_NAMESPACE = __NAMESPACE__ . '\Entity';

    private const int RANDOM_BYTES_LEN = 32;

    /**
     * @throws Exception
     */
    public static function generateSecret(int $length = self::RANDOM_BYTES_LEN): string
    {
        return substr(md5(random_bytes(self::RANDOM_BYTES_LEN)), 0, $length);
    }
}
