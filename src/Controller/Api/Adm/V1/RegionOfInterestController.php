<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\RegionOfInterest\RegionOfInterestFacade;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\RegionOfInterest\RegionOfInterestAdmListDto;
use AnzuSystems\CoreDamBundle\Repository\Decorator\RegionOfInterestRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '', name: 'adm_roi_v1_')]
#[OA\Tag('RegionOfInterest')]
final class RegionOfInterestController extends AbstractApiController
{
    public function __construct(
        private readonly RegionOfInterestFacade $regionOfInterestFacade,
        private readonly RegionOfInterestRepositoryDecorator $repositoryDecorator,
    ) {
    }

    /**
     * Create an image with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/image/{image}/roi', name: 'create', methods: [Request::METHOD_POST])]
    #[
        OAParameterPath('image'),
        OARequest(RegionOfInterestAdmDetailDto::class),
        OAResponse(RegionOfInterestAdmDetailDto::class),
        OAResponseValidation
    ]
    public function create(Request $request, ImageFile $image, #[SerializeParam] RegionOfInterestAdmDetailDto $regionOfInterest): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_REGION_OF_INTEREST_CREATE, $image);
        $regionOfInterest = $this->regionOfInterestFacade->create($image, $regionOfInterest);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $regionOfInterest);

        return $this->createdResponse(RegionOfInterestAdmDetailDto::getInstance($regionOfInterest));
    }

    /**
     * Get list of assets
     *
     * @throws ORMException
     */
    #[Route('/image/{image}/roi', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAParameterPath('image'), OAResponse([RegionOfInterestAdmListDto::class])]
    public function getList(ImageFile $image, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_REGION_OF_INTEREST_READ, $image);

        return $this->okResponse(
            $this->repositoryDecorator->findByApiParamsWithInfiniteListing($apiParams, $image),
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/roi/{regionOfInterest}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(RegionOfInterestAdmDetailDto::class)]
    public function getOne(RegionOfInterest $regionOfInterest): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_REGION_OF_INTEREST_READ, $regionOfInterest);

        return $this->okResponse(RegionOfInterestAdmDetailDto::getInstance($regionOfInterest));
    }

    /**
     * Get one item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/roi/{regionOfInterest}', name: 'update', methods: [Request::METHOD_PUT])]
    #[
        OAParameterPath('regionOfInterest'),
        OARequest(RegionOfInterestAdmDetailDto::class),
        OAResponse(RegionOfInterestAdmDetailDto::class),
        OAResponseValidation
    ]
    public function update(Request $request, RegionOfInterest $regionOfInterest, #[SerializeParam] RegionOfInterestAdmDetailDto $roiDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_REGION_OF_INTEREST_UPDATE, $regionOfInterest);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $regionOfInterest);

        return $this->okResponse(
            RegionOfInterestAdmDetailDto::getInstance($this->regionOfInterestFacade->update($regionOfInterest, $roiDto))
        );
    }

    /**
     * Delete item.
     *
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/roi/{regionOfInterest}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('regionOfInterest'), OAResponseDeleted]
    public function delete(Request $request, RegionOfInterest $regionOfInterest): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_REGION_OF_INTEREST_DELETE, $regionOfInterest);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $regionOfInterest);

        $this->regionOfInterestFacade->delete($regionOfInterest);

        return $this->noContentResponse();
    }
}
