<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\FileNameHelper;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractImageController extends AbstractPublicController
{
    public const CROP_EXTENSION = 'jpeg';
    public const DEFAULT_CROP_MIME_TYPE = 'image/jpeg';

    private FileSystemProvider $fileSystemProvider;

    #[Required]
    public function setFileSystemProvider(FileSystemProvider $fileSystemProvider): void
    {
        $this->fileSystemProvider = $fileSystemProvider;
    }

    protected function streamOriginalResponse(AssetFile $assetFile): StreamedResponse
    {
        $fileSystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);

        $response = new StreamedResponse(
            callback: function () use ($fileSystem, $assetFile) {
                $outputStream = fopen('php://output', 'wb');
                stream_copy_to_stream(
                    $fileSystem->readStream($assetFile->getAssetAttributes()->getFilePath()),
                    $outputStream
                );
            },
            status: Response::HTTP_OK,
            headers: [
                'Content-Type' => $assetFile->getAssetAttributes()->getMimeType(),
                'Content-Disposition' => $this->makeDisposition($assetFile),
                'Content-Length' => $assetFile->getAssetAttributes()->getSize(),
            ]
        );
        $this->assetFileCacheManager->setCache($response, $assetFile);

        return $response;
    }

    protected function okResponse(string $content, AssetFile $asset): Response
    {
        $response = $this->getImageResponse($content, $asset)->setStatusCode(Response::HTTP_OK);
        $this->assetFileCacheManager->setCache($response, $asset);

        return $response;
    }

    protected function notFoundResponse(string $content, AssetFile $asset): Response
    {
        $response = $this->getImageResponse($content, $asset)->setStatusCode(Response::HTTP_NOT_FOUND);
        $this->assetFileCacheManager->setNotFoundCache($response);

        return $response;
    }

    protected function getImageResponse(string $content, AssetFile $assetFile): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => self::DEFAULT_CROP_MIME_TYPE,
            'Content-Disposition' => $this->makeDisposition($assetFile),
            'Content-Length' => strlen($content),
        ]);
    }

    private function makeDisposition(AssetFile $assetFile): string
    {
        $fileName = FileNameHelper::changeFileExtension(
            (string) $assetFile->getId(),
            self::CROP_EXTENSION
        );

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $fileName,
            (new UnicodeString($fileName))->ascii()->toString()
        );
    }
}
