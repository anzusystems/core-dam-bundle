<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionPermissionFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Model\Decorator\DistributionServiceAuthorization;
use AnzuSystems\CoreDamBundle\Repository\Decorator\DistributionRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
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
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{distribution}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distribution'), OAResponse(Distribution::class)]
    public function getOne(Distribution $distribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distribution);

        return $this->okResponse($distribution);
    }

    /**
     * @throws ORMException
     */
    #[Route('/asset-file/{assetFile}', name: 'asset_file_distribution_list', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAParameterPath('assetFile'), OAResponse([Distribution::class])]
    public function getAssetFileDistributionList(AssetFile $assetFile, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetFile);

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

    /**
     * @throws ORMException
     */
    #[Route('/asset/{asset}', name: 'asset_distribution_list', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAParameterPath('assetFile'), OAResponse([Distribution::class])]
    public function getAssetDistributionList(Asset $asset, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $asset);

        return $this->okResponse(
            $this->distributionRepository->findByApiParamsByAsset($apiParams, $asset)
        );
    }
}
