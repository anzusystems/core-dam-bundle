<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model;

final class TtsNarrationRequestAdmUrl
{
    private const int API_VERSION = 1;

    public static function synthesize(): string
    {
        return sprintf('/api/adm/v%d/tts-narration-request/synthesize', self::API_VERSION);
    }

    public static function getOne(string $id): string
    {
        return sprintf('/api/adm/v%d/tts-narration-request/%s', self::API_VERSION, $id);
    }

    public static function cancel(string $id): string
    {
        return sprintf('/api/adm/v%d/tts-narration-request/%s/cancel', self::API_VERSION, $id);
    }

    public static function listByExtSystem(int $extSystemId): string
    {
        return sprintf('/api/adm/v%d/tts-narration-request/ext-system/%d', self::API_VERSION, $extSystemId);
    }
}
