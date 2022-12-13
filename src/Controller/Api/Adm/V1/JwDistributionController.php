<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\JwDistribution\JwDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JwDistribution;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/jw-distribution', name: 'adm_jw_distribution_v1_')]
#[OA\Tag('JwDistribution')]
final class JwDistributionController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionFacade $distributionFacade,
        private readonly JwDistributionFacade $jwDistributionFacade,
    ) {
    }


    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/asset-file/{assetFile}/distribute', name: 'distribute', methods: [Request::METHOD_POST])]
    #[ParamConverter('jwDistribution', converter: SerializerParamConverter::class)]
    #[OARequest(JwDistribution::class), OAParameterPath('assetFile'), OAResponse(JwDistribution::class), OAResponseValidation]
    public function distribute(AssetFile $assetFile, JwDistribution $jwDistribution): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $jwDistribution->getDistributionService());

        return $this->okResponse(
            $this->distributionFacade->distribute($assetFile, $jwDistribution)
        );
    }


    /**
     * @throws NonUniqueResultException
     */
    #[Route('/asset-file/{assetFile}/prepare-payload/{distributionService}', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAParameterPath('distributionService'), OAResponse(JwDistribution::class)]
    public function preparePayload(AssetFile $assetFile, string $distributionService): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->jwDistributionFacade->preparePayload($assetFile, $distributionService)
        );
    }
}
