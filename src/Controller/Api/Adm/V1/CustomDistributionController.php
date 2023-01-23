<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\CustomDistribution\CustomDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/custom-distribution', name: 'adm_custom_distribution_v1_')]
#[OA\Tag('CustomDistribution')]
final class CustomDistributionController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionFacade $distributionFacade,
        private readonly CustomDistributionFacade $customDistributionFacade,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     */
    #[Route('/asset-file/{assetFile}/distribute', name: 'distribute_custom', methods: [Request::METHOD_POST])]
    public function distributeCustom(AssetFile $assetFile, #[SerializeParam] CustomDistribution $customDistribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $customDistribution->getDistributionService());

        return $this->okResponse(
            $this->distributionFacade->distribute($assetFile, $customDistribution)
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/asset-file/{assetFile}/prepare-payload/{distributionService}', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAParameterPath('distributionService'), OAResponse(YoutubeDistribution::class)]
    public function preparePayload(AssetFile $assetFile, string $distributionService): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetFile);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->customDistributionFacade->preparePayload($assetFile, $distributionService)
        );
    }
}
