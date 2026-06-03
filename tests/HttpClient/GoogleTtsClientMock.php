<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mocks the Google Cloud Text-to-Speech HTTP API. `POST /v1/text:synthesize` returns a JSON body with
 * base64-encoded bytes of a real sample MP3 ({@see tests/data/Files/audiob}), matching Google's
 * `audioContent` contract so the provider's base64 decode + concat pipeline runs for real.
 */
final class GoogleTtsClientMock
{
    private const string SAMPLE_MP3 = 'audiob';

    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []): MockResponse => $this->getResponse($url)
        );
    }

    private function getResponse(string $url): MockResponse
    {
        if ('/v1/text:synthesize' === UrlHelper::parseUrl($url)->getPath()) {
            return new MockResponse(
                (string) json_encode(['audioContent' => base64_encode($this->sampleMp3())]),
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
