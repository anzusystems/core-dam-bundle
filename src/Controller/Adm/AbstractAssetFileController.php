<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Adm;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\FileSystem\LocalFilesystem;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Contracts\Service\Attribute\Required;

#[AsController]
abstract class AbstractAssetFileController extends AbstractController
{
    use FileHelperTrait;

    private FileSystemProvider $fileSystemProvider;

    #[Required]
    public function setFileSystemProvider(FileSystemProvider $fileSystemProvider): void
    {
        $this->fileSystemProvider = $fileSystemProvider;
    }

    /**
     * @throws FilesystemException
     */
    public function getDownloadResponse(AssetFile $assetFile): Response
    {
        $storage = $this->fileSystemProvider->getFilesystemByStorable($assetFile);
        if (false === $storage instanceof LocalFilesystem) {
            throw new ForbiddenOperationException(ForbiddenOperationException::NOT_ALLOWED_DOWNLOAD);
        }

        $stream = $storage->readStream($assetFile->getAssetAttributes()->getFilePath());

        $response = new StreamedResponse(static function () use ($stream) {
            $outputStream = fopen('php://output', 'wb');
            stream_copy_to_stream($stream, $outputStream);
            flush();
        });

        $fileName = $assetFile->getId() . '.' . $this->fileHelper->guessExtension(
            $assetFile->getAssetAttributes()->getMimeType()
        );

        $response->headers->set('Content-Type', $assetFile->getAssetAttributes()->getMimeType());
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName
            )
        );
        $response->headers->set('Content-Length', (string) $assetFile->getAssetAttributes()->getSize());

        return $response;
    }
}
