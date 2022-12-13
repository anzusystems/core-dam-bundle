<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Adm;

use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use League\Flysystem\FilesystemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/image', name: 'adm_image_')]
#[OA\Tag('Image')]
final class ImageFileController extends AbstractAssetFileController
{
    /**
     * @throws FilesystemException
     */
    #[Route(path: '/{image}/download', name: 'download', methods: [Request::METHOD_GET])]
    #[OAParameterPath('image')]
    public function download(ImageFile $image): Response
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_VIEW, $image);

        return $this->getDownloadResponse($image);
    }
}
