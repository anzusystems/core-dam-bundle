<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Model\ValueObject\Url;

final class UrlHelper
{
    public static function concatPathWithDomain(string $domain, string $path): string
    {
        if (str_ends_with($domain, '/')) {
            $domain = substr($domain, 0, -1);
        }

        return sprintf(
            '%s/%s',
            $domain,
            $path,
        );
    }

    public static function isValidUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        return false;
    }

    public static function parseUrl(string $url): Url
    {
        return Url::createFromArray(
            parse_url($url)
        );
    }
}
