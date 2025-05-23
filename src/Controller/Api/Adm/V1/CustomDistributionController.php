<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\CustomDistribution\CustomDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Model\Dto\CustomDistribution\CustomDistributionAdmDto;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Repository\Decorator\DistributionRepositoryDecorator;
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
        private readonly CustomDistributionFacade $customDistributionFacade,
        private readonly DistributionRepositoryDecorator $distributionRepository,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     */
    #[Route('/asset-file/{assetFile}/distribute', name: 'distribute_custom', methods: [Request::METHOD_POST])]
    public function distributeCustom(AssetFile $assetFile, #[SerializeParam] CustomDistributionAdmDto $customDistribution): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $customDistribution->getDistributionService());

        return $this->okResponse(
            $this->distributionRepository->decorate(
                $this->customDistributionFacade->distribute($assetFile, $customDistribution)
            )
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{distribution}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('distribution'), OAResponseDeleted]
    public function delete(Request $request, Distribution $distribution): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distribution);

        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $distribution);
        $this->customDistributionFacade->delete($distribution);

        return $this->noContentResponse();
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{distribution}/redistribute', name: 'redistribute', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('distribution'), OAResponse(YoutubeDistribution::class), OAResponseValidation]
    public function redistribute(Request $request, Distribution $distribution, #[SerializeParam] CustomDistributionAdmDto $customDistribution): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $this->assetRepository->find($distribution->getAssetId()));
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distribution->getDistributionService());

        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $distribution);

        return $this->okResponse(
            $this->distributionRepository->decorate(
                $this->customDistributionFacade->redistribute($distribution, $customDistribution)
            )
        );
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/asset-file/{assetFile}/prepare-payload/{distributionService}', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAParameterPath('distributionService'), OAResponse(CustomDistributionAdmDto::class)]
    public function preparePayload(AssetFile $assetFile, string $distributionService): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetFile);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->distributionRepository->decorate(
                $this->customDistributionFacade->preparePayload($assetFile, $distributionService)
            )
        );
    }
}
