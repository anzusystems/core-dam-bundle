<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class UrlFileFactory
{
    private const int TIMEOUT = 600;
    private const int MAX_DURATION = 600;

    public function __construct(
        private FileSystemProvider $fileSystemProvider,
        private HttpClientInterface $client,
        private DamLogger $damLogger,
    ) {
    }

    /**
     * @throws AssetFileProcessFailed
     * @throws SerializerException
     */
    public function downloadFile(string $url): AdapterFile
    {
        try {
            $response = $this->client->request(
                method: Request::METHOD_GET,
                url: $url,
                options: [
                    'timeout' => self::TIMEOUT,
                    'max_duration' => self::MAX_DURATION,
                ]
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

            throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
        }
    }
}
