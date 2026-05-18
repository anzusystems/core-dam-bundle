<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use Symfony\Component\HttpFoundation\Response;

final class TtsProviderExceptionHandler extends AbstractTtsExceptionHandler
{
    public const string ERROR = 'tts_provider_unavailable';

    public function getSupportedExceptionClasses(): array
    {
        return [TtsProviderException::class];
    }

    protected function errorCode(): string
    {
        return self::ERROR;
    }

    protected function httpStatus(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
