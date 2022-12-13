<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Adm;

use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use League\Flysystem\FilesystemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/video', name: 'adm_video_')]
#[OA\Tag('Video')]
final class VideoFileController extends AbstractAssetFileController
{
    /**
     * @throws FilesystemException
     */
    #[Route(path: '/{video}/download', name: 'download', methods: [Request::METHOD_GET])]
    #[OAParameterPath('video')]
    public function download(VideoFile $video): Response
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_VIEW, $video);

        return $this->getDownloadResponse($video);
    }
}
