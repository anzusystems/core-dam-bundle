<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\PublicExport\PublicExportFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\PublicExport;
use AnzuSystems\CoreDamBundle\Repository\PublicExportRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/public-export', name: 'adm_publicExport_v1_')]
#[OA\Tag('PublicExport')]
final class PublicExportController extends AbstractApiController
{
    public function __construct(
        private readonly PublicExportFacade $publicExportFacade,
        private readonly PublicExportRepository $publicExportRepository,
    ) {
    }

    /**
     * @throws ORMException
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(AssetLicence::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PUBLIC_EXPORT_READ);

        return $this->okResponse(
            $this->publicExportRepository->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Get one item.
     */
    #[Route('/{publicExport}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('publicExport'), OAResponse(PublicExport::class)]
    public function getOne(PublicExport $publicExport): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PUBLIC_EXPORT_READ, $publicExport);

        return $this->okResponse($publicExport);
    }

    /**
     * Create one item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(PublicExport::class), OAResponse(PublicExport::class), OAResponseValidation]
    public function create(#[SerializeParam] PublicExport $publicExport): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PUBLIC_EXPORT_CREATE, $publicExport);

        return $this->createdResponse(
            $this->publicExportFacade->create($publicExport)
        );
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{publicExport}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('publicExport'), OARequest(PublicExport::class), OAResponse(PublicExport::class), OAResponseValidation]
    public function update(PublicExport $publicExport, #[SerializeParam] PublicExport $newPublicExport): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PUBLIC_EXPORT_UPDATE, $publicExport);

        return $this->okResponse(
            $this->publicExportFacade->update($publicExport, $newPublicExport)
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{publicExport}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('publicExport'), OAResponseDeleted]
    public function delete(PublicExport $publicExport): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PUBLIC_EXPORT_DELETE, $publicExport);

        $this->publicExportFacade->delete($publicExport);

        return $this->noContentResponse();
    }
}
