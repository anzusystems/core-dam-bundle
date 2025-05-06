<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\DistributionCategory\DistributionCategoryFacade;
use AnzuSystems\CoreDamBundle\Entity\DistributionCategory;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\DistributionCategoryAdmRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/distribution/category', name: 'adm_distribution_category_v1_')]
#[OA\Tag('DistributionCategory')]
final class DistributionCategoryController extends AbstractApiController
{
    public function __construct(
        private readonly DistributionCategoryFacade $distributionCategoryFacade,
        private readonly DistributionCategoryAdmRepositoryDecorator $distributionCategoryAdmRepositoryDecorator,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{distributionCategory}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionCategory'), OAResponse(DistributionCategory::class)]
    public function getOne(DistributionCategory $distributionCategory): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_READ, $distributionCategory);

        return $this->okResponse($distributionCategory);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAParameterPath('extSystem'), OAParameterPath('type'), OAResponse([DistributionCategory::class])]
    public function getList(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_READ, $extSystem);

        return $this->okResponse(
            $this->distributionCategoryAdmRepositoryDecorator->findByApiParams(
                apiParams: $apiParams,
                extSystem: $extSystem,
            ),
        );
    }

    /**
     * Create item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(DistributionCategory::class), OAResponseCreated(DistributionCategory::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] DistributionCategory $distributionCategory): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_CREATE, $distributionCategory);

        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $distributionCategory);

        return $this->createdResponse(
            $this->distributionCategoryFacade->create($distributionCategory)
        );
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{distributionCategory}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('distributionCategory'), OARequest(DistributionCategory::class), OAResponse(DistributionCategory::class), OAResponseValidation]
    public function update(
        Request $request,
        DistributionCategory $distributionCategory,
        #[SerializeParam]
        DistributionCategory $newDistributionCategory,
    ): JsonResponse {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_CATEGORY_UPDATE, $distributionCategory);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $distributionCategory);

        return $this->okResponse(
            $this->distributionCategoryFacade->update($distributionCategory, $newDistributionCategory)
        );
    }
}
