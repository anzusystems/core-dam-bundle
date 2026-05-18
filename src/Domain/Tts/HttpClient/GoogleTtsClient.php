<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Request-only wrapper around the scoped `tts.google.api.client`. Manual error handling — response
 * carries large base64 audio that would balloon journal logs via loggedRequest.
 */
final readonly class GoogleTtsClient
{
    public const string HEADER_AUTHORIZATION = 'Authorization';
    private const string PATH_SYNTHESIZE = '/v1/text:synthesize';

    public function __construct(
        private HttpClientInterface $ttsGoogleApiClient,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws TtsProviderException
     */
    public function synthesize(string $accessToken, array $body): HttpClientResponse
    {
        try {
            $response = $this->ttsGoogleApiClient->request(
                Request::METHOD_POST,
                self::PATH_SYNTHESIZE,
                [
                    'headers' => [self::HEADER_AUTHORIZATION => 'Bearer ' . $accessToken],
                    'json' => $body,
                ],
            );

            return new HttpClientResponse(content: $response->getContent(false), statusCode: $response->getStatusCode());
        } catch (ExceptionInterface $e) {
            throw new TtsProviderException(sprintf('Google TTS request failed: %s', $e->getMessage()), 0, $e);
        }
    }
}
