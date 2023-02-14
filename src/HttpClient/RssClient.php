<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\HttpClient;

use AnzuSystems\CommonBundle\Traits\SerializerAwareTrait;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class RssClient
{
    use SerializerAwareTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly DamLogger $logger,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function readPodcastRss(Podcast $podcast): string
    {
        try {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                $podcast->getAttributes()->getRssUrl()
            );

            return $response->getContent();
        } catch (Throwable $exception) {
            $this->logger->error(
                DamLogger::NAMESPACE_PODCAST_RSS_IMPORT,
                sprintf('Failed read rss feed (%s)', (string) $podcast->getId())
            );

            throw new RuntimeException(message: $exception->getMessage(), previous: $exception);
        }
    }
}
