<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUsageConflictDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class ImageUsageConflictExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param ImageUsageConflictException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $exception->getMessage(),
                'conflicts' => array_map(
                    static fn (ImageUsageConflictDto $conflict): array => [
                        'damId' => $conflict->getDamId(),
                        'holderName' => $conflict->getHolderName(),
                        'holderId' => $conflict->getHolderId(),
                    ],
                    $exception->getConflicts(),
                ),
                'contextId' => App::getContextId(),
            ],
            // 409, not 422: a refused take over (ForbiddenOperationException) already answers 422, and the
            // caller has to tell "the licence rules forbid this" from "someone else holds one of these photos".
            JsonResponse::HTTP_CONFLICT
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [ImageUsageConflictException::class];
    }
}
