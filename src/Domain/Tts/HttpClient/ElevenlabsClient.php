<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Request-only wrapper around the scoped `tts.elevenlabs.api.client`. The API key is supplied
 * per-call so a single client instance can serve multiple ExtSystem tenants.
 */
final readonly class ElevenlabsClient
{
    public const string HEADER_X_API_KEY = 'xi-api-key';
    public const string HEADER_REQUEST_ID = 'request-id';
    private const string PATH_TEXT_TO_SPEECH = '/v1/text-to-speech/';

    public function __construct(
        private HttpClientInterface $ttsElevenlabsApiClient,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws TtsProviderException
     */
    public function synthesize(string $externalVoiceId, string $apiKey, array $body): ElevenlabsResponse
    {
        try {
            $response = $this->ttsElevenlabsApiClient->request(
                Request::METHOD_POST,
                self::PATH_TEXT_TO_SPEECH . $externalVoiceId,
                [
                    'headers' => [self::HEADER_X_API_KEY => $apiKey],
                    'json' => $body,
                ],
            );

            return new ElevenlabsResponse(
                http: new HttpClientResponse(content: $response->getContent(false), statusCode: $response->getStatusCode()),
                requestId: $response->getHeaders(false)[self::HEADER_REQUEST_ID][0] ?? null,
            );
        } catch (ExceptionInterface $e) {
            throw new TtsProviderException(sprintf('ElevenLabs request failed: %s', $e->getMessage()), 0, $e);
        }
    }
}
