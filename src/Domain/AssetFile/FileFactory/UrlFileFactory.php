<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileFactory;

use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UrlFileFactory
{
    public function __construct(
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws TransportExceptionInterface
     */
    public function downloadFile(string $url): File
    {
        $response = $this->client->request(
            Request::METHOD_GET,
            $url,
        );

        $fileSystem = $this->fileSystemProvider->getTmpFileSystem();
        $baseFile = $fileSystem->writeTmpFileFromStream(StreamWrapper::createResource($response));

        return File::createFromBaseFile($baseFile, $fileSystem);
    }
}
