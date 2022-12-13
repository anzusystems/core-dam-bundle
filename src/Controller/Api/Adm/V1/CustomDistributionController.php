<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\CustomDistribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/custom-distribution', name: 'adm_custom_distribution_v1_')]
#[OA\Tag('CustomDistribution')]
final class CustomDistributionController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionFacade $distributionFacade,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     */
    #[Route('/asset-file/{assetFile}/distribute', name: 'distribute_custom', methods: [Request::METHOD_POST])]
    #[ParamConverter('customDistribution', converter: SerializerParamConverter::class)]
    public function distributeCustom(VideoFile $assetFile, CustomDistribution $customDistribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $customDistribution->getDistributionService());

        return $this->okResponse(
            $this->distributionFacade->distribute($assetFile, $customDistribution)
        );
    }
}
