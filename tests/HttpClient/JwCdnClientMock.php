<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class JwCdnClientMock
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

        if (str_starts_with($url->getPath(), '/v2/media')) {
            return new MockResponse(
                json_encode([
                    'title' => 'title',
                    'description' => 'title',
                    'playlist' => [
                        [
                            'sources' => [
                                [
                                    'file' => 'direct-url',
                                    'type' => 'video/mp4',
                                    'height' => 500,
                                    'width' => 500,
                                    'filesize' => 1000000,
                                ]
                            ]
                        ]
                    ]
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
