<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Model\Attributes\ArrayStringParam;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CoreDamBundle\ApiFilter\AssetExternalProviderApiParams;
use AnzuSystems\CoreDamBundle\AssetExternalProvider\AssetExternalProviderContainer;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\AssetExternalProviderDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/asset-external-provider', name: 'adm_asset_external_provider_v1_')]
#[OA\Tag('AssetExternalProvider')]
final class AssetExternalProviderController extends AbstractApiController
{
    private const int IDS_LIMIT = 50;

    public function __construct(
        private readonly AssetExternalProviderContainer $providerContainer,
    ) {
    }

    #[Route('/{providerName}/search', name: 'search_by_provider_service', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched.'), OAResponse([AssetExternalProviderDto::class])]
    public function searchByProviderService(string $providerName, AssetExternalProviderApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $providerName);

        return $this->okResponse(
            $this->providerContainer->get($providerName)->search($apiParams),
        );
    }

    /**
     * @param list<string> $ids
     */
    #[Route('/{providerName}/ids/{ids}', name: 'get_by_provider_service_ids', methods: [Request::METHOD_GET])]
    #[OAParameterPath('ids', description: 'List of ids.'), OAResponse([AssetExternalProviderDto::class])]
    public function getByProviderService(
        string $providerName,
        #[ArrayStringParam(itemsLimit: self::IDS_LIMIT)] array $ids,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $providerName);

        return $this->okResponse(
            $this->providerContainer->get($providerName)->getByIds($ids),
        );
    }

    #[Route('/{providerName}/{id}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetExternalProviderDto::class)]
    public function getOneByProviderService(string $providerName, string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $providerName);

        return $this->okResponse(
            $this->providerContainer->get($providerName)->getById($id),
        );
    }
}
