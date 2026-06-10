<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CoreDamBundle\App;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

abstract class AbstractTtsExceptionHandler implements ExceptionHandlerInterface
{
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $this->errorCode(),
                'detail' => $exception->getMessage(),
                'contextId' => App::getContextId(),
            ],
            $this->httpStatus(),
        );
    }

    abstract protected function errorCode(): string;

    abstract protected function httpStatus(): int;
}
