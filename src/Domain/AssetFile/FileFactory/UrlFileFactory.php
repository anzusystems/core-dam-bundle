<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class UrlFileFactory
{
    private const int TIMEOUT = 600;
    private const int MAX_DURATION = 600;
    private const string HTTPS_SCHEME = 'https';

    private HttpClientInterface $trustedClient;
    private HttpClientInterface $safeClient;

    /**
     * @var list<string>
     */
    private array $urlFileTrustedDomains;

    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        HttpClientInterface $client,
        private DamLogger $damLogger,
        private LoggerInterface $appLogger,
        string $urlFileTrustedDomains = '',
    ) {
        $this->trustedClient = $client;
        $this->safeClient = new NoPrivateNetworkHttpClient($client);
        $this->urlFileTrustedDomains = array_filter(array_map('trim', explode(',', $urlFileTrustedDomains)));
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws SerializerException
     */
    public function downloadFile(string $url): AdapterFile
    {
        $trusted = $this->isTrustedDomain($url);

        if (false === $trusted && self::HTTPS_SCHEME !== parse_url($url, PHP_URL_SCHEME)) {
            throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
        }

        $options = [
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
        ];
        if (false === $trusted) {
            $options['max_redirects'] = 0;
        }

        try {
            $client = $trusted ? $this->trustedClient : $this->safeClient;
            $response = $client->request(
                method: Request::METHOD_GET,
                url: $url,
                options: $options,
            );

            if (Response::HTTP_BAD_REQUEST <= $response->getStatusCode()) {
                throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
            }

            $fileSystem = $this->fileSystemProvider->getTmpFileSystem();
            $baseFile = $fileSystem->writeTmpFileFromStream(StreamWrapper::createResource($response));

            return AdapterFile::createFromBaseFile($baseFile, $fileSystem);
        } catch (Throwable $e) {
            $this->damLogger->error(
                DamLogger::NAMESPACE_ASSET_FILE_DOWNLOAD,
                sprintf(
                    'Failed To download file from url (%s). Failed message (%s)',
                    $url,
                    $e->getMessage()
                )
            );
            $this->appLogger->error($e->getMessage(), ['exception' => $e]);

            throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
        }
    }

    private function isTrustedDomain(string $url): bool
    {
        try {
            $host = strtolower(UrlHelper::parseUrl($url)->getHost());
        } catch (InvalidArgumentException) {
            return false;
        }

        foreach ($this->urlFileTrustedDomains as $domain) {
            $domain = strtolower($domain);
            if (str_starts_with($domain, '.')) {
                $bare = ltrim($domain, '.');
                if ($host === $bare || str_ends_with($host, $domain)) {
                    return true;
                }
            } elseif ($host === $domain) {
                return true;
            }
        }

        return false;
    }
}
