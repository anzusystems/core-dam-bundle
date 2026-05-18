<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use Symfony\Component\HttpFoundation\Response;

final class ImmutableAudioNarrationExceptionHandler extends AbstractTtsExceptionHandler
{
    public const string ERROR = 'immutable_audio_narration';

    public function getSupportedExceptionClasses(): array
    {
        return [ImmutableAudioNarrationException::class];
    }

    protected function errorCode(): string
    {
        return self::ERROR;
    }

    protected function httpStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
