<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class AuthorCleanPhraseUrl
{
    private const int API_VERSION = 1;

    public static function getOne(int $id): string
    {
        return sprintf('/api/adm/v%d/author-clean-phrase/%d', self::API_VERSION, $id);
    }

    public static function update(int $id): string
    {
        return sprintf('/api/adm/v%d/author-clean-phrase/%d', self::API_VERSION, $id);
    }

    public static function getList(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/author-clean-phrase/ext-system/%d', self::API_VERSION, $extSystemId);
    }

    public static function create(): string
    {
        return sprintf('/api/adm/v%d/author-clean-phrase', self::API_VERSION);
    }

    public static function playground(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/author-clean-phrase/ext-system/%d/playground', self::API_VERSION, $extSystemId);
    }
}
