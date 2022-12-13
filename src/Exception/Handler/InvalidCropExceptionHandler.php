<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class InvalidCropExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param ForbiddenOperationException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $exception->getMessage(),
                'detail' => $exception->getDetail(),
                'contextId' => App::getContextId(),
            ],
            JsonResponse::HTTP_BAD_REQUEST
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [InvalidCropException::class];
    }
}
