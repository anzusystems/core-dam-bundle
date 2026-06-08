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
     * Text containing this marker forces the provider to return 500, enabling failure-path testing.
     */
    public const string FORCE_FAIL_MARKER = 'TTS_FORCE_FAIL';
    private const string SAMPLE_MP3 = 'audioa';

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
            if (str_contains((string) ($options['body'] ?? ''), self::FORCE_FAIL_MARKER)) {
                return new MockResponse(
                    (string) json_encode(['detail' => ['status' => 'mock_forced_failure']]),
                    ['http_code' => Response::HTTP_INTERNAL_SERVER_ERROR],
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
