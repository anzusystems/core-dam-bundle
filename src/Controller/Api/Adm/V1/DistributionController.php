<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionPermissionFacade;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionUpdateFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\Decorator\DistributionAdmElasticsearchDecorator;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\DistributionAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionServiceAuthorization;
use AnzuSystems\CoreDamBundle\Model\Domain\Distribution\AbstractDistributionUpdateDto;
use AnzuSystems\CoreDamBundle\Repository\Decorator\DistributionRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/distribution', name: 'adm_distribution_v1_')]
#[OA\Tag('Distribution')]
final class DistributionController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionRepositoryDecorator $distributionRepository,
        private readonly DistributionPermissionFacade $distributionPermissionFacade,
        private readonly DistributionAdmElasticsearchDecorator $elasticSearch,
        private readonly DistributionUpdateFacade $distributionUpdateFacade,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     */
    #[Route('/search', name: 'search', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched.'), OAResponse([Distribution::class])]
    public function search(#[SerializeParam] DistributionAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_VIEW, $searchDto);

        return $this->okResponse($this->elasticSearch->searchInfiniteList($searchDto));
    }

    /**
     * Get one item.
     */
    #[Route('/{distribution}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distribution'), OAResponse(Distribution::class)]
    public function getOne(Distribution $distribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distribution);

        return $this->okResponse($this->distributionRepository->decorate($distribution));
    }

    /**
     * @throws ORMException
     */
    #[Route('/asset/{asset}', name: 'asset_distribution_list', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAResponse([Distribution::class])]
    public function getAssetDistributionList(Asset $asset, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $asset);

        return $this->okResponse(
            $this->distributionRepository->findByApiParamsByAsset($apiParams, $asset)
        );
    }

    /**
     * @throws ORMException
     */
    #[Route('/asset-file/{assetFile}', name: 'asset_file_distribution_list', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAResponse([Distribution::class])]
    public function getAssetFileDistributionList(AssetFile $assetFile, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetFile);

        return $this->okResponse(
            $this->distributionRepository->findByApiParamsByAssetFile($apiParams, $assetFile)
        );
    }

    #[Route('/{distributionService}/authorized', name: 'available_', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionService'), OAResponse([DistributionServiceAuthorization::class])]
    public function getAvailableDistribution(string $distributionService): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->distributionPermissionFacade->isDistributionServiceAuthorized($distributionService)
        );
    }

    #[Route('/asset/{asset}', name: 'asset_update_distributions', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('distributionService'), OAResponse([DistributionServiceAuthorization::class])]
    public function upsertDistributions(Asset $asset, AbstractDistributionUpdateDto $update): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $update->getDistributionService());
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $update->getAssetFile());

        return $this->okResponse(
            $this->distributionUpdateFacade->upsert($asset, $update)
        );
    }

    #[Route('/{distribution}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('distributionService'), OAResponse([DistributionServiceAuthorization::class])]
    public function deleteDistribution(Distribution $distribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_DELETE, $distribution);

        $this->distributionUpdateFacade->delete($distribution);

        return $this->noContentResponse();
    }
}
