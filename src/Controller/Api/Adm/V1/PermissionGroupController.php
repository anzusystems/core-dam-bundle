<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Domain\PermissionGroup\PermissionGroupFacade;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Entity\PermissionGroup;
use AnzuSystems\CoreDamBundle\Repository\PermissionGroupRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('', 'adm_permissionGroup_v1_')]
#[OA\Tag('PermissionGroup')]
final class PermissionGroupController extends AbstractApiController
{
    public function __construct(
        private readonly PermissionGroupFacade $permissionGroupFacade,
        private readonly PermissionGroupRepository $permissionGroupRepo
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/permission-group/{permissionGroup}', 'get_one', ['permissionGroup' => '\d+'], methods: [Request::METHOD_GET])]
    #[OAParameterPath('permissionGroup'), OAResponse(PermissionGroup::class)]
    public function getOne(PermissionGroup $permissionGroup): JsonResponse
    {
        return $this->okResponse($permissionGroup);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('/permission-group', 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseList(PermissionGroup::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->permissionGroupRepo->findByApiParams($apiParams),
        );
    }

    /**
     * Create item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/permission-group', 'create', methods: [Request::METHOD_POST])]
    #[OARequest(PermissionGroup::class), OAResponseCreated(PermissionGroup::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] PermissionGroup $permissionGroup): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PERMISSION_GROUP_CREATE);
        $permissionGroup = $this->permissionGroupFacade->create($permissionGroup);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $permissionGroup);

        return $this->createdResponse($permissionGroup);
    }

    /**
     * Update item.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/permission-group/{permissionGroup}', 'update', ['permissionGroup' => '\d+'], methods: [Request::METHOD_PUT])]
    #[OAParameterPath('permissionGroup'), OARequest(PermissionGroup::class), OAResponse(PermissionGroup::class), OAResponseValidation]
    public function update(Request $request, PermissionGroup $permissionGroup, #[SerializeParam] PermissionGroup $newPermissionGroup): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PERMISSION_GROUP_UPDATE, $permissionGroup);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $permissionGroup);

        return $this->okResponse(
            $this->permissionGroupFacade->update($permissionGroup, $newPermissionGroup)
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route('/permission-group/{permissionGroup}', 'delete', ['permissionGroup' => '\d+'], methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('permissionGroup'), OAResponseDeleted]
    public function delete(Request $request, PermissionGroup $permissionGroup): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PERMISSION_GROUP_DELETE, $permissionGroup);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $permissionGroup);
        $this->permissionGroupFacade->delete($permissionGroup);

        return $this->noContentResponse();
    }
}
