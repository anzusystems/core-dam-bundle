<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

/** mbstring/iconv charset names used while recovering IPTC values; not exiftool's own spelling (`utf8`, `latin1`). */
enum ExifCharset: string implements EnumInterface
{
    use BaseEnumTrait;

    case Utf8 = 'UTF-8';
    case Ascii = 'ASCII';

    /** Maps bytes 1:1 onto U+0000..U+00FF, which is how exiftool's latin1 passthrough read is undone. */
    case BytePassthrough = 'ISO-8859-1';
}
