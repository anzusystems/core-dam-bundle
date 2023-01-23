<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategorySelect\DistributionCategorySelectFacade;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategorySelect;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\DistributionCategorySelectAdmRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/distribution/category-select', name: 'adm_distribution_category_select_v1_')]
#[OA\Tag('DistributionCategorySelect')]
final class DistributionCategorySelectController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionCategorySelectFacade $distributionCategorySelectFacade,
        private readonly DistributionCategorySelectAdmRepositoryDecorator $distributionCategorySelectAdmRepoDecorator,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{distributionCategorySelect}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionCategorySelect'), OAResponse(DistributionCategorySelect::class)]
    public function getOne(DistributionCategorySelect $distributionCategorySelect): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_SELECT_VIEW, $distributionCategorySelect);

        return $this->okResponse($distributionCategorySelect);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list', methods: [Request::METHOD_GET])]
    #[Route('/ext-system/{extSystem}/asset-type/{assetType}', name: 'get_list_with_asset_type', methods: [Request::METHOD_GET])]
    #[OAParameterPath('extSystem'), OAParameterPath('assetType'),  OAResponse([DistributionCategorySelect::class])]
    public function getList(ApiParams $apiParams, ExtSystem $extSystem, AssetType $assetType = null): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_SELECT_VIEW, $extSystem);

        return $this->okResponse(
            $this->distributionCategorySelectAdmRepoDecorator->findByApiParams(
                apiParams: $apiParams,
                extSystem: $extSystem,
                type: $assetType,
            ),
        );
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{distributionCategorySelect}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('distributionCategorySelect'), OARequest(DistributionCategorySelect::class), OAResponse(DistributionCategorySelect::class), OAResponseValidation]
    public function update(
        DistributionCategorySelect $distributionCategorySelect,
        #[SerializeParam] DistributionCategorySelect $newDistributionCategorySelect,
    ): JsonResponse {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_SELECT_UPDATE, $distributionCategorySelect);

        return $this->okResponse(
            $this->distributionCategorySelectFacade->update($distributionCategorySelect, $newDistributionCategorySelect)
        );
    }
}
