<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileFailedType;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class UrlFileFactory
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @throws AssetFileProcessFailed
     */
    public function downloadFile(string $url): AdapterFile
    {
        try {
            $response = $this->client->request(
                Request::METHOD_GET,
                $url,
            );

            $fileSystem = $this->fileSystemProvider->getTmpFileSystem();
            $baseFile = $fileSystem->writeTmpFileFromStream(StreamWrapper::createResource($response));

            return AdapterFile::createFromBaseFile($baseFile, $fileSystem);
        } catch (Throwable) {
            throw new AssetFileProcessFailed(AssetFileFailedType::DownloadFailed);
        }
    }
}
