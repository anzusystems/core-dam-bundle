<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastRssReader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class RssPodcastMock extends AbstractFileMock
{
    public const string FIRST_RSS_DATE_MODEFIER = '-6 weeks';
    public const string SECOND_RSS_DATE_MODEFIER = '-8 weeks';
    public const string THIRD_RSS_DATE_MODEFIER = '-10 weeks';
    private const string FIRST_PUB_DATE_RSS_PLACEHOLDER = '__FirstPubDatePlaceholder__';
    private const string SECOND_PUB_DATE_RSS_PLACEHOLDER = '__SecondPubDatePlaceholder__';
    private const string THIRD_PUB_DATE_RSS_PLACEHOLDER = '__ThirdPubDatePlaceholder__';
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
        $fileContent = '';
        if ('https://anchor.fm/s/8a651488/podcast/rss' === $url) {
            $fileContent = $this->getTestDataFile( 'firstPodcast.xml');
        }
        if ('https://anchor.fm/s/4d8e8b48/podcast/rss' === $url) {
            $fileContent = $this->getTestDataFile( 'secondPodcast.xml');
        }
        if ('https://anchor.fm/s/7758ecd4/podcast/rss' === $url) {
            $fileContent = $this->getTestDataFile( 'thirdPodcast.xml');
        }
        $firstEpisodeDate = App::getAppDate()->modify(self::FIRST_RSS_DATE_MODEFIER)->format(PodcastRssReader::RSS_DATE_FORMAT);
        $secondEpisodeDate = App::getAppDate()->modify(self::SECOND_RSS_DATE_MODEFIER)->format(PodcastRssReader::RSS_DATE_FORMAT);
        $thirdEpisodeDate = App::getAppDate()->modify(self::THIRD_RSS_DATE_MODEFIER)->format(PodcastRssReader::RSS_DATE_FORMAT);

        $fileContent = str_replace(self::FIRST_PUB_DATE_RSS_PLACEHOLDER, $firstEpisodeDate, $fileContent);
        $fileContent = str_replace(self::SECOND_PUB_DATE_RSS_PLACEHOLDER, $secondEpisodeDate, $fileContent);

        return str_replace(self::THIRD_PUB_DATE_RSS_PLACEHOLDER, $thirdEpisodeDate, $fileContent);
    }
}
