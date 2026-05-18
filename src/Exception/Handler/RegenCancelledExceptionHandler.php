<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CoreDamBundle\Exception\RegenCancelledException;
use Symfony\Component\HttpFoundation\Response;

final class RegenCancelledExceptionHandler extends AbstractTtsExceptionHandler
{
    public const string ERROR = 'regen_cancelled';

    public function getSupportedExceptionClasses(): array
    {
        return [RegenCancelledException::class];
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
