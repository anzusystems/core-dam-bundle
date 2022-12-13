<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemFacade;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/ext-system', name: 'adm_ext_system_v1_')]
#[OA\Tag('ExtSystem')]
final class ExtSystemController extends AbstractApiController
{
    public function __construct(
        private readonly ExtSystemFacade $extSystemFacade,
        private readonly ExtSystemRepository $extSystemRepo,
    ) {
    }

    /**
     * Get one item.
     */
    #[Route('/{extSystem}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('extSystem'), OAResponse(ExtSystem::class)]
    public function getOne(ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_EXT_SYSTEM_VIEW, $extSystem);

        return $this->okResponse($extSystem);
    }

    /**
     * Get list of items.
     *
     * @throws ORMException
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([ExtSystem::class])]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_EXT_SYSTEM_LIST);

        return $this->okResponse(
            $this->extSystemRepo->findByApiParams($apiParams),
        );
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{extSystem}', name: 'update', methods: [Request::METHOD_PUT])]
    #[ParamConverter('newExtSystem', converter: SerializerParamConverter::class)]
    #[OAParameterPath('extSystem'), OARequest(ExtSystem::class), OAResponse(ExtSystem::class), OAResponseValidation]
    public function update(ExtSystem $extSystem, ExtSystem $newExtSystem): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_EXT_SYSTEM_UPDATE, $extSystem);

        return $this->okResponse(
            $this->extSystemFacade->update($extSystem, $newExtSystem)
        );
    }
}
