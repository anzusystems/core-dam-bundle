<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetLicenceGroup\AssetLicenceGroupFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicenceGroup;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceGroupRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/asset-licence-group', name: 'adm_asset_licence_group_v1_')]
#[OA\Tag('AssetLicenceGroup')]
final class AssetLicenceGroupController extends AbstractApiController
{
    public function __construct(
        private readonly AssetLicenceGroupFacade $facade,
        private readonly AssetLicenceGroupRepository $repository,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{assetLicenceGroup}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetLicenceGroup'), OAResponse(AssetLicenceGroup::class)]
    public function getOne(AssetLicenceGroup $assetLicenceGroup): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_GROUP_VIEW, $assetLicenceGroup);

        return $this->okResponse($assetLicenceGroup);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(AssetLicenceGroup::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_GROUP_LIST);

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
    #[OARequest(AssetLicenceGroup::class), OAResponseCreated(AssetLicenceGroup::class), OAResponseValidation]
    public function create(#[SerializeParam] AssetLicenceGroup $licenceGroup): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_GROUP_CREATE);

        return $this->createdResponse(
            $this->facade->create($licenceGroup)
        );
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{assetLicenceGroup}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('assetLicence'), OARequest(AssetLicenceGroup::class), OAResponse(AssetLicenceGroup::class), OAResponseValidation]
    public function update(AssetLicenceGroup $assetLicenceGroup, #[SerializeParam] AssetLicenceGroup $newAssetLicenceGroup): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_GROUP_UPDATE, $assetLicenceGroup);

        return $this->okResponse(
            $this->facade->update($assetLicenceGroup, $newAssetLicenceGroup)
        );
    }
}
