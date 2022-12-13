<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Adm;

use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use League\Flysystem\FilesystemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/document', name: 'adm_document_')]
#[OA\Tag('Document')]
final class DocumentFileController extends AbstractAssetFileController
{
    /**
     * @throws FilesystemException
     */
    #[Route(path: '/{document}/download', name: 'download', methods: [Request::METHOD_GET])]
    #[OAParameterPath('document')]
    public function download(DocumentFile $document): Response
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_VIEW, $document);

        return $this->getDownloadResponse($document);
    }
}
