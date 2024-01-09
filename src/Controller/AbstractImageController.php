<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DomainProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\FileNameHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractImageController extends AbstractPublicController
{
    public const CROP_EXTENSION = 'jpeg';
    public const DEFAULT_CROP_MIME_TYPE = 'image/jpeg';
    protected DomainProvider $domainProvider;
    protected ImageFileRepository $imageFileRepository;

    private FileSystemProvider $fileSystemProvider;
    private CropFacade $cropFacade;
    private ConfigurationProvider $configurationProvider;

    #[Required]
    public function setFileSystemProvider(FileSystemProvider $fileSystemProvider): void
    {
        $this->fileSystemProvider = $fileSystemProvider;
    }

    #[Required]
    public function setCropFacade(CropFacade $cropFacade): void
    {
        $this->cropFacade = $cropFacade;
    }

    #[Required]
    public function setDomainProvider(DomainProvider $domainProvider): void
    {
        $this->domainProvider = $domainProvider;
    }

    #[Required]
    public function setConfigurationProvider(ConfigurationProvider $configurationProvider): void
    {
        $this->configurationProvider = $configurationProvider;
    }

    #[Required]
    public function setImageFileRepository(ImageFileRepository $imageFileRepository): void
    {
        $this->imageFileRepository = $imageFileRepository;
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     */
    protected function okImageResponse(
        ImageFile $image,
        RegionOfInterest $roi,
        RequestedCropDto $cropPayload
    ): Response {
        $response = $this->getImageResponse(
            content: $this->cropFacade->applyCropPayload($image, $cropPayload, $roi),
            assetFile: $image,
        )->setStatusCode(Response::HTTP_OK);
        $this->assetFileCacheManager->setCache($response, $image);

        return $response;
    }

    protected function okResponse(string $content, AssetFile $asset): Response
    {
        $response = $this->getImageResponse($content, $asset)->setStatusCode(Response::HTTP_OK);
        $this->assetFileCacheManager->setCache($response, $asset);

        return $response;
    }

    protected function notFoundResponse(): Response
    {
        $response = new Response('', Response::HTTP_NOT_FOUND);
        $this->assetFileCacheManager->setNotFoundCache($response);

        return $response;
    }

    /**
     * @throws FilesystemException
     * @throws ImageManipulatorException
     * @throws InvalidCropException
     * @throws NonUniqueResultException
     */
    protected function notFoundImageResponse(RequestedCropDto $cropPayload): Response
    {
        $notFoundImageId = $this->configurationProvider->getSettings()->getNotFoundImageId();
        if (empty($notFoundImageId)) {
            return $this->notFoundResponse();
        }

        $notFoundImage = $this->imageFileRepository->findProcessedById($notFoundImageId);
        if (null === $notFoundImage) {
            return $this->notFoundResponse();
        }

        $notFoundRoi = $notFoundImage->getRegionsOfInterest()->first();
        if ($notFoundRoi instanceof RegionOfInterest) {
            $response = $this->getImageResponse(
                content: $this->cropFacade->applyCropPayload($notFoundImage, $cropPayload, $notFoundRoi),
                assetFile: $notFoundImage,
            )->setStatusCode(Response::HTTP_OK);
            $this->assetFileCacheManager->setNotFoundCache($response);

            return $response;
        }

        return $this->notFoundResponse();
    }

    /**
     * @throws FilesystemException
     */
    protected function streamResponse(AssetFile $assetFile): StreamedResponse
    {
        $filesystem = $this->fileSystemProvider->getFilesystemByStorable($assetFile);
        $fileStream = $filesystem->readStream($assetFile->getAssetAttributes()->getFilePath());

        $response = new StreamedResponse(
            function () use ($fileStream) {
                $outputStream = fopen('php://output', 'wb');
                stream_copy_to_stream($fileStream, $outputStream);
            },
            Response::HTTP_OK,
            [
                'Content-Transfer-Encoding' => 'binary',
                'Content-Type' => $assetFile->getAssetAttributes()->getMimeType(),
                'Content-Length' => fstat($fileStream)['size'],
            ]
        );
        $this->assetFileCacheManager->setCache(
            $response,
            $assetFile
        );

        return $response;
    }

    private function getImageResponse(string $content, AssetFile $assetFile): Response
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
