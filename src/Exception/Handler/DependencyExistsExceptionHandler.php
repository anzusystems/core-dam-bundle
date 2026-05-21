<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\DependencyExistsException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class DependencyExistsExceptionHandler implements ExceptionHandlerInterface
{
    public const string ERROR = DependencyExistsException::ERROR_MESSAGE;

    public function getSupportedExceptionClasses(): array
    {
        return [DependencyExistsException::class];
    }

    /**
     * @param DependencyExistsException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'dependencies' => $exception->getDependencies(),
                'contextId' => App::getContextId(),
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
