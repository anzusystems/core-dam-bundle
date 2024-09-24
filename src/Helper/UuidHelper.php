<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\App;
use Symfony\Component\Routing\Requirement\Requirement;

final class UuidHelper
{
    public static function isUuid(string $string): bool
    {
        return App::ZERO < preg_match('/^' . Requirement::UUID . '$/', $string);
    }
}
