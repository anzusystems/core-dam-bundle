<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class BaseClient
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

        if ('/jw-upload-link' === $url->getPath()) {
            return new MockResponse(
                '',
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
}
