<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetLicence\AssetLicenceFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/asset-licence', name: 'adm_asset_licence_v1_')]
#[OA\Tag('AssetLicence')]
final class AssetLicenceController extends AbstractApiController
{
    public function __construct(
        private readonly AssetLicenceFacade $assetLicenceFacade,
        private readonly AssetLicenceRepository $assetLicenceRepo,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{assetLicence}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetLicence'), OAResponse(AssetLicence::class)]
    public function getOne(AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_READ, $assetLicence);

        return $this->okResponse($assetLicence);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(AssetLicence::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_LIST);

        return $this->okResponse(
            $this->assetLicenceRepo->findByApiParamsWithInfiniteListing($apiParams),
        );
    }

    /**
     * Create item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(AssetLicence::class), OAResponseCreated(AssetLicence::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_CREATE);
        $assetLicence = $this->assetLicenceFacade->create($assetLicence);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetLicence);

        return $this->createdResponse(
            $assetLicence
        );
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{assetLicence}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('assetLicence'), OARequest(AssetLicence::class), OAResponse(AssetLicence::class), OAResponseValidation]
    public function update(Request $request, AssetLicence $assetLicence, #[SerializeParam] AssetLicence $newAssetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_LICENCE_UPDATE, $assetLicence);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetLicence);

        return $this->okResponse(
            $this->assetLicenceFacade->update($assetLicence, $newAssetLicence)
        );
    }
}
