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
        $fileName = FileNameHelper::changeFileExtension(
            (string) $asset->getId(),
            self::CROP_EXTENSION
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $fileName,
            (new UnicodeString($fileName))->ascii()->toString()
        );

        $response = new Response($content, Response::HTTP_OK, [
            'Content-Type' => self::DEFAULT_CROP_MIME_TYPE,
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($content),
        ]);
        $this->setCache($response, $asset);

        return $response;
    }

    protected function notFoundResponse(): Response
    {
        return new Response(
            'Image not found',
            Response::HTTP_NOT_FOUND,
        );
    }
}
