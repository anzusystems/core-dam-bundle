<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;

final readonly class ElevenlabsResponse
{
    public function __construct(
        public HttpClientResponse $http,
        public ?string $requestId,
    ) {
    }
}
