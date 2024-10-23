<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\Model\ValueObject\Url;
use Symfony\Component\Routing\Requirement\Requirement;

final class UrlHelper
{
    public static function getImageIdFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        preg_match('/^\/image\/w\d+\-h\d+(-c\d+)?(-q\d+)?\/(?<imageId>' . Requirement::UUID . ')\.jpg$/', $path, $matches);

        return $matches['imageId'] ?? null;
    }

    public static function concatPathWithDomain(string $domain, string $path): string
    {
        if (str_ends_with($domain, '/')) {
            $domain = substr($domain, 0, -1);
        }
        if (false === str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return sprintf(
            '%s%s',
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
