<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\ApiFilter\LicensedEntityApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\VideoShow\VideoShowFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\LicensedEntityFilter;
use AnzuSystems\CoreDamBundle\Repository\VideoShowRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/video-show', name: 'adm_video_show_v1_')]
#[OA\Tag('VideoShow')]
final class VideoShowController extends AbstractApiController
{
    public function __construct(
        private readonly VideoShowFacade $videoShowFacade,
        private readonly VideoShowRepository $videoShowRepository,
    ) {
    }

    #[Route('/{videoShow}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('videoShow'), OAResponse(VideoShow::class)]
    public function getOne(VideoShow $videoShow): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_READ, $videoShow);

        return $this->okResponse($videoShow);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(VideoShow::class), OAResponse(VideoShow::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] VideoShow $videoShow): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_CREATE, $videoShow);
        $videoShow = $this->videoShowFacade->create($videoShow);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoShow);

        return $this->createdResponse($videoShow);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{videoShow}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('videoShow'), OARequest(VideoShow::class), OAResponse(VideoShow::class), OAResponseValidation]
    public function update(Request $request, VideoShow $videoShow, #[SerializeParam] VideoShow $newVideoShow): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_UPDATE, $videoShow);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoShow);

        return $this->okResponse(
            $this->videoShowFacade->update($videoShow, $newVideoShow)
        );
    }

    /**
     * @throws ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(VideoShow::class)]
    public function getListByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_READ, $extSystem);

        return $this->okResponse($this->videoShowRepository->findByApiParamsWithInfiniteListing(
            apiParams: LicensedEntityApiParams::applyCustomFilter($apiParams, $extSystem),
            customFilters: [new LicensedEntityFilter()]
        ));
    }

    /**
     * @throws ORMException
     */
    #[Route('/licence/{assetLicence}', name: 'get_list_by_asset_licence', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(VideoShow::class)]
    public function getListByLicence(ApiParams $apiParams, AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_READ, $assetLicence);

        return $this->okResponse($this->videoShowRepository->findByApiParamsWithInfiniteListing(
            apiParams: LicensedEntityApiParams::applyLicenceCustomFilter($apiParams, $assetLicence),
            customFilters: [new LicensedEntityFilter()]
        ));
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{videoShow}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('videoShow'), OAResponseDeleted]
    public function delete(Request $request, VideoShow $videoShow): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_DELETE, $videoShow);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoShow);
        $this->videoShowFacade->delete($videoShow);

        return $this->noContentResponse();
    }
}
