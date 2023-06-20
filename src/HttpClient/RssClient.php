<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\Traits\LoggerAwareRequest;
use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerAwareInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RssClient implements LoggerAwareInterface
{
    use SerializerAwareTrait;
    use LoggerAwareRequest;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function readPodcastRss(Podcast $podcast): string
    {
        $response = $this->loggedRequest(
            client: $this->httpClient,
            message: '[RssRead] read rss file',
            url: $podcast->getAttributes()->getRssUrl(),
            logSuccess: false
        );

        return $response->getContent();
    }
}
