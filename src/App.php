<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle;

use AnzuSystems\Contracts\AnzuApp;
use Exception;

final class App extends AnzuApp
{
    public const ZERO = 0;
    public const DOCTRINE_EXTRA_LAZY = 'EXTRA_LAZY';

    public const ENTITY_NAMESPACE = __NAMESPACE__ . '\Entity';

    private const RANDOM_BYTES_LEN = 32;

    /**
     * @throws Exception
     */
    public static function generateSecret(int $length = self::RANDOM_BYTES_LEN): string
    {
        return substr(md5(random_bytes(self::RANDOM_BYTES_LEN)), 0, $length);
    }
}
