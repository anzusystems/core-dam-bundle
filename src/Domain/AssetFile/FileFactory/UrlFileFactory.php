<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
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
    public const int TRUSTED_MAX_REDIRECTS = 5;
    public const int MAX_DOWNLOAD_BYTES = 2_147_483_648;
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
        bool $urlFileAllowPrivateNetworks = false,
    ) {
        // Trust only relaxes https-only and allows capped redirects; the private-network guard stays on
        // every redirect hop. The dev-only flag lifts it so local domains (sme.local → 127.0.0.1) work.
        $this->trustedClient = $urlFileAllowPrivateNetworks ? $client : new NoPrivateNetworkHttpClient($client);
        $this->safeClient = new NoPrivateNetworkHttpClient($client);
        $this->urlFileTrustedDomains = array_values(array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            explode(',', $urlFileTrustedDomains),
        )));
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws SerializerException
     */
    public function downloadFile(string $url): AdapterFile
    {
        try {
            $parsedUrl = UrlHelper::parseUrl($url);
        } catch (InvalidArgumentException) {
            throw $this->rejectDownload('malformed_url');
        }

        $trusted = $this->isTrustedDomain(strtolower($parsedUrl->getHost()));
        if (false === $trusted && self::HTTPS_SCHEME !== strtolower($parsedUrl->getScheme())) {
            throw $this->rejectDownload('untrusted_non_https', $parsedUrl->getHost(), $parsedUrl->getScheme());
        }

        // Loggable form without userinfo credentials and query tokens.
        $safeUrl = sprintf('%s://%s%s', $parsedUrl->getScheme(), $parsedUrl->getHost(), $parsedUrl->getPath());

        $options = [
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
            'max_redirects' => $trusted ? self::TRUSTED_MAX_REDIRECTS : 0,
            'on_progress' => static function (int $dlNow, int $dlSize): void {
                if ($dlNow > self::MAX_DOWNLOAD_BYTES || $dlSize > self::MAX_DOWNLOAD_BYTES) {
                    throw new RuntimeException(sprintf('download_size_exceeded (max %d bytes)', self::MAX_DOWNLOAD_BYTES));
                }
            },
        ];

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
                    $safeUrl,
                    $e->getMessage()
                )
            );
            $this->appLogger->error($e->getMessage(), ['exception' => $e]);

            throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
        }
    }

    private function rejectDownload(string $reason, string $host = '', string $scheme = ''): AssetFileProcessFailed
    {
        // Host+scheme only — the full url may carry userinfo credentials.
        $this->damLogger->warning(
            DamLogger::NAMESPACE_ASSET_FILE_DOWNLOAD,
            sprintf('Url download rejected (%s) host (%s) scheme (%s)', $reason, $host, $scheme),
        );

        return new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
    }

    private function isTrustedDomain(string $host): bool
    {
        foreach ($this->urlFileTrustedDomains as $domain) {
            if ($host === ltrim($domain, '.')) {
                return true;
            }
            if (str_starts_with($domain, '.') && str_ends_with($host, $domain)) {
                return true;
            }
        }

        return false;
    }
}
