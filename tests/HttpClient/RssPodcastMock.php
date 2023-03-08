<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class RssPodcastMock extends AbstractFileMock
{
    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []) => $this->getResponse($method, $url, $options)
        );
    }

    private function getResponse(string $method, string $url, array $options = []): MockResponse
    {
        return new MockResponse(
            $this->getContent($url),
            [
                'http_code' => Response::HTTP_OK,
            ]
        );
    }

    private function getContent(string $url): string
    {
        if ('https://feed.podbean.com/vedatorskypodcast/feed.xml' === $url) {
            return $this->getTestDataFile( 'vedator.xml');
        }
        if ('https://www.thisamericanlife.org/podcast/rss.xml' === $url) {
            return $this->getTestDataFile( 'americanLife.xml');
        }

        return '';
    }
}
