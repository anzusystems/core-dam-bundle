<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider\GoogleSynthesizeRequestDto;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Manual error handling: base64 audio response would bloat journal logs. */
final readonly class GoogleTtsClient
{
    public const string HEADER_AUTHORIZATION = 'Authorization';
    private const string PATH_SYNTHESIZE = '/v1/text:synthesize';

    public function __construct(
        private HttpClientInterface $ttsGoogleApiClient,
        private Serializer $serializer,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    public function synthesize(string $accessToken, GoogleSynthesizeRequestDto $request): HttpClientResponse
    {
        try {
            /** @var array<string, mixed> $body */
            $body = $this->serializer->toArray($request);
            $response = $this->ttsGoogleApiClient->request(
                Request::METHOD_POST,
                self::PATH_SYNTHESIZE,
                [
                    'headers' => [
                        self::HEADER_AUTHORIZATION => 'Bearer ' . $accessToken,
                    ],
                    'json' => $body,
                ],
            );

            return new HttpClientResponse(content: $response->getContent(false), statusCode: $response->getStatusCode());
        } catch (ExceptionInterface $e) {
            throw TtsProviderException::transient(sprintf('Google TTS request failed: %s', $e->getMessage()), $e);
        }
    }
}
