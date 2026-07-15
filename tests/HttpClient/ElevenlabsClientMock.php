<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/** Mocks ElevenLabs API; returns real sample MP3 bytes so the ffmpeg concat pipeline runs for real. */
final class ElevenlabsClientMock
{
    /**
     * Text containing this marker forces the provider to return 400 (permanent failure path).
     */
    public const string FORCE_FAIL_MARKER = 'TTS_FORCE_FAIL';

    /**
     * Text containing this marker forces the provider to return 500 (transient failure path —
     * the chunk is re-armed and the exception rethrown for transport redelivery).
     */
    public const string FORCE_TRANSIENT_FAIL_MARKER = 'TTS_FORCE_TRANSIENT_FAIL';
    private const string SAMPLE_MP3 = 'audioa';

    /**
     * Decoded text-to-speech request payloads, captured for assertions (e.g. previous_request_ids gating).
     *
     * @var list<array<string, mixed>>
     */
    public array $sentBodies = [];

    /**
     * @var list<array<string, string>>
     */
    public array $sentQueries = [];

    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []): MockResponse => $this->getResponse($url, $options)
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getResponse(string $url, array $options): MockResponse
    {
        $path = UrlHelper::parseUrl($url)->getPath();

        if (str_starts_with($path, '/v1/text-to-speech/')) {
            $body = (string) ($options['body'] ?? '');
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $this->sentBodies[] = $decoded;
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            /** @var array<string, string> $query */
            $this->sentQueries[] = $query;
            if (str_contains($body, self::FORCE_TRANSIENT_FAIL_MARKER)) {
                return new MockResponse(
                    (string) json_encode(['detail' => ['status' => 'mock_forced_transient_failure']]),
                    ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR],
                );
            }
            if (str_contains($body, self::FORCE_FAIL_MARKER)) {
                return new MockResponse(
                    (string) json_encode(['detail' => ['status' => 'mock_forced_failure']]),
                    ['http_code' => Response::HTTP_BAD_REQUEST],
                );
            }

            return new MockResponse($this->sampleMp3(), [
                'http_code' => Response::HTTP_OK,
                'response_headers' => [
                    'request-id' => 'mock-elevenlabs-request-id',
                    'content-type' => 'audio/mpeg',
                ],
            ]);
        }

        if ('/v1/voices' === $path) {
            return new MockResponse(
                (string) json_encode(['voices' => [['voice_id' => 'test-elevenlabs-voice', 'name' => 'Mock Voice']]]),
                ['http_code' => Response::HTTP_OK],
            );
        }

        return new MockResponse('', ['http_code' => Response::HTTP_NOT_FOUND]);
    }

    private function sampleMp3(): string
    {
        return (string) file_get_contents(App::getProjectDir() . '/tests/data/Files/' . self::SAMPLE_MP3);
    }
}
