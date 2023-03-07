<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class JwClientMock
{
    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []) => $this->getResponse($method, $url, $options)
        );
    }

    private function getResponse(string $method, string $url, array $options = []): MockResponse
    {
        $url = UrlHelper::parseUrl($url);

        if ('/v2/sites/site_id/media' === $url->getPath()) {
            return new MockResponse(
                json_encode([
                    'id' => '123jw',
                    'upload_link' => 'https://api.jw.com/jw-upload-link'
                ]),
                [
                    'http_code' => Response::HTTP_OK,
                ]
            );
        }
        if ('/v2/sites/site_id/media/123jw/' === $url->getPath()) {
            return new MockResponse(
                json_encode([
                    'status' => 'ready'
                ]),
                [
                    'http_code' => Response::HTTP_OK,
                ]
            );
        }

        return new MockResponse(
            '',
            [
                'http_code' => Response::HTTP_NOT_FOUND,
            ]
        );
    }

    private function getContent(string $url): string
    {

        return '';
    }
}
