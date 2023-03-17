<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\HttpClient;

use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\ValueObject\Url;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class DownloadFileClient extends AbstractFileMock
{
    private const DOWNLOAD_PATH = '/download-file';

    public function __invoke(): MockHttpClient
    {
        return new MockHttpClient(
            fn (string $method, string $url, array $options = []) => $this->getResponse($method, $url, $options)
        );
    }

    private function getResponse(string $method, string $url, array $options = []): MockResponse
    {
        $url = UrlHelper::parseUrl($url);

        if (self::DOWNLOAD_PATH === $url->getPath()) {
            return $this->getDownloadResponse($url);
        }

        return new MockResponse(
            '',
            [
                'http_code' => Response::HTTP_OK,
            ]
        );
    }

    private function getDownloadResponse(Url $url): MockResponse
    {
        $fileName = $url->getQueryParams()['file'] ?? '';

        return new MockResponse(
            $this->getTestDataFile($fileName),
            [
                'http_code' => Response::HTTP_OK,
                'response_headers' => [
                    'content-type' => $this->getTestDataMime($fileName),
                ],
            ]
        );
    }
}
