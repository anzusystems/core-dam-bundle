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
        if ('https://anchor.fm/s/8a651488/podcast/rss' === $url) {
            return $this->getTestDataFile( 'firstPodcast.xml');
        }
        if ('https://anchor.fm/s/4d8e8b48/podcast/rss' === $url) {
            return $this->getTestDataFile( 'secondPodcast.xml');
        }
        if ('https://anchor.fm/s/7758ecd4/podcast/rss' === $url) {
            return $this->getTestDataFile( 'thirdPodcast.xml');
        }

        return '';
    }
}
