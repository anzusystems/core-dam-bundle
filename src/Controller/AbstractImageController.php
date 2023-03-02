<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Helper\FileNameHelper;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\UnicodeString;

abstract class AbstractImageController extends AbstractPublicController
{
    public const CROP_EXTENSION = 'jpeg';
    public const DEFAULT_CROP_MIME_TYPE = 'image/jpeg';

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
        $fileName = FileNameHelper::changeFileExtension(
            (string) $assetFile->getId(),
            self::CROP_EXTENSION
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $fileName,
            (new UnicodeString($fileName))->ascii()->toString()
        );

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => self::DEFAULT_CROP_MIME_TYPE,
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($content),
        ]);
    }
}
