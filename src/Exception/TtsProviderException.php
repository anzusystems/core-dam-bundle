<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;
use AnzuSystems\CoreDamBundle\App;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TtsProviderException extends AnzuException
{
    private bool $transient = false;

    /**
     * Retryable provider failure (rate limit, provider outage, network) — chunk handlers re-arm and
     * let the transport redeliver instead of failing the whole request terminally.
     */
    public static function transient(string $message, ?Throwable $previous = null): self
    {
        $exception = new self($message, App::ZERO, $previous);
        $exception->transient = true;

        return $exception;
    }

    public static function fromHttpStatus(int $statusCode, string $message): self
    {
        return Response::HTTP_TOO_MANY_REQUESTS === $statusCode || Response::HTTP_INTERNAL_SERVER_ERROR <= $statusCode
            ? self::transient($message)
            : new self($message);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
