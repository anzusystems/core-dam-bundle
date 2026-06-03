<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mocks the ElevenLabs text-to-speech HTTP API. `POST /v1/text-to-speech/{voiceId}` returns the raw bytes
 * of a real sample MP3 ({@see tests/data/Files/audioa}) so the chunk concat + ffmpeg pipeline runs for real;
 * each chunk request returns the same blob, so an N-chunk synthesis concatenates to ~N× the sample duration.
 */
final class ElevenlabsClientMock
{
    private const string SAMPLE_MP3 = 'audioa';

    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []): MockResponse => $this->getResponse($url)
        );
    }

    private function getResponse(string $url): MockResponse
    {
        $path = UrlHelper::parseUrl($url)->getPath();

        if (str_starts_with($path, '/v1/text-to-speech/')) {
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
