<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetListView\AssetListViewFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetListView;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\AssetListViewRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/asset-list-view', name: 'adm_asset_list_view_v1_')]
#[OA\Tag('AssetListView')]
final class AssetListViewController extends AbstractApiController
{
    public function __construct(
        private readonly AssetListViewFacade $facade,
        private readonly AssetListViewRepository $repository,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{assetListView}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetListView'), OAResponse(AssetListView::class)]
    public function getOne(AssetListView $assetListView): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LIST_VIEW_READ, $assetListView);

        return $this->okResponse($assetListView);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(AssetListView::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LIST_VIEW_LIST);

        return $this->okResponse(
            $this->repository->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Create item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(AssetListView::class), OAResponseCreated(AssetListView::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] AssetListView $assetListView): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LIST_VIEW_CREATE);
        $assetListView = $this->facade->create($assetListView);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetListView);

        return $this->createdResponse($assetListView);
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{assetListView}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('assetListView'), OARequest(AssetListView::class), OAResponse(AssetListView::class), OAResponseValidation]
    public function update(Request $request, AssetListView $assetListView, #[SerializeParam] AssetListView $newAssetListView): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LIST_VIEW_UPDATE, $assetListView);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetListView);

        return $this->okResponse(
            $this->facade->update($assetListView, $newAssetListView)
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route('/{assetListView}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('assetListView'), OAResponseDeleted]
    public function delete(Request $request, AssetListView $assetListView): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LIST_VIEW_DELETE, $assetListView);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetListView);
        $this->facade->delete($assetListView);

        return $this->noContentResponse();
    }
}
