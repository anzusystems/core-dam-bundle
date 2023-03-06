<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use JsonException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RssMockClient extends CoreDamKernelTestCase
{
    /**
     * @throws JsonException
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {

//        if (BeamClient::PATH_UPSERT === $url->getPath()) {
//            return array_shift($this->upsertArticleResponses) ?: new MockResponse(
//                json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR)
//            );
//        }
//        if (BeamClient::PATH_FETCH_PAGE_VIEWS === $url->getPath()) {
//            return array_shift($this->fetchPageViewsResponses) ?: new MockResponse(
//                json_encode([], JSON_THROW_ON_ERROR)
//            );
//        }
//        if (BeamClient::PATH_FETCH_TOP_PAGE_VIEWS === $url->getPath()) {
//            return array_shift($this->fetchTopPageViewsResponses) ?: new MockResponse(
//                json_encode([], JSON_THROW_ON_ERROR)
//            );
//        }

        return new MockResponse();
    }
}