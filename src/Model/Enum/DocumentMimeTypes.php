<?php

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DocumentMimeTypes: string implements EnumInterface
{
    use BaseEnumTrait;

    public const CHOICES = [
        self::MIME_PDF,
        self::TEXT_PLAIN,
    ];

    private const MIME_PDF = 'application/pdf';
    private const TEXT_PLAIN = 'text/plain';

    case mimePdf = self::MIME_PDF;
}
