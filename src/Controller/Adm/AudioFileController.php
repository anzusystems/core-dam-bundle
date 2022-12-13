<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Adm;

use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use League\Flysystem\FilesystemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/audio', name: 'adm_audio_')]
#[OA\Tag('Audio')]
final class AudioFileController extends AbstractAssetFileController
{
    /**
     * @throws FilesystemException
     */
    #[Route(path: '/{audio}/download', name: 'download', methods: [Request::METHOD_GET])]
    #[OAParameterPath('audio')]
    public function download(AudioFile $audio): Response
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_VIEW, $audio);

        return $this->getDownloadResponse($audio);
    }
}
