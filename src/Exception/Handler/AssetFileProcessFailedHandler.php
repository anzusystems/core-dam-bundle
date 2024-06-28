<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AssetFileProcessFailedHandler implements ExceptionHandlerInterface
{
    /**
     * @param AssetFileProcessFailed $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $exception->getMessage(),
                'detail' => $exception->getAssetFileFailedType()->toString(),
                'contextId' => App::getContextId(),
            ],
            match ($exception->getAssetFileFailedType()) {
                AssetFileFailedType::None,
                AssetFileFailedType::Unknown => Response::HTTP_INTERNAL_SERVER_ERROR,
                AssetFileFailedType::InvalidChecksum,
                AssetFileFailedType::InvalidMimeType,
                AssetFileFailedType::DownloadFailed,
                AssetFileFailedType::InvalidSize => Response::HTTP_BAD_REQUEST
            }
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [AssetFileProcessFailed::class];
    }
}
