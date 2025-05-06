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
use AnzuSystems\CoreDamBundle\ApiFilter\VideoShowEpisodeApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode\VideoShowEpisodeBodyFacade;
use AnzuSystems\CoreDamBundle\Domain\VideoShowEpisode\VideoShowEpisodeFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use AnzuSystems\CoreDamBundle\Entity\VideoShowEpisode;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\VideoShowEpisodeFilter;
use AnzuSystems\CoreDamBundle\Repository\VideoShowEpisodeRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/video-show-episode', name: 'adm_video_show_episode_episode_v1_')]
#[OA\Tag('VideoShowEpisode')]
final class VideoShowEpisodeController extends AbstractApiController
{
    public function __construct(
        private readonly VideoShowEpisodeRepository $videoShowEpisodeRepository,
        private readonly VideoShowEpisodeFacade $videoShowEpisodeFacade,
        private readonly VideoShowEpisodeBodyFacade $videoShowEpisodeBodyFacade,
    ) {
    }

    #[Route('/{videoShowEpisode}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('VideoShowEpisode'), OAResponse(VideoShowEpisode::class)]
    public function getOne(VideoShowEpisode $videoShowEpisode): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_READ, $videoShowEpisode);

        return $this->okResponse($videoShowEpisode);
    }

    #[Route('/asset/{asset}/video-show/{videoShow}/prepare-payload', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAResponse(VideoShowEpisode::class)]
    public function preparePayload(Asset $asset, VideoShow $videoShow): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_READ, $videoShow);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $asset);

        return $this->okResponse($this->videoShowEpisodeBodyFacade->preparePayload($asset, $videoShow));
    }

    /**
     * @throws ORMException
     */
    #[Route('/video-show/{videoShow}', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(VideoShowEpisode::class)]
    public function getList(VideoShow $videoShow, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_READ, $videoShow);

        return $this->okResponse($this->videoShowEpisodeRepository->findByApiParamsWithInfiniteListing(
            apiParams: VideoShowEpisodeApiParams::applyCustomFilter($apiParams, $videoShow),
            customFilters: [new VideoShowEpisodeFilter()],
        ));
    }

    /**
     * @throws ORMException
     */
    #[Route('/asset/{asset}', name: 'get_list_by_asset', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(VideoShowEpisode::class)]
    public function getListByAsset(Asset $asset, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_READ, $asset);

        return $this->okResponse($this->videoShowEpisodeRepository->findByApiParamsWithInfiniteListing(
            apiParams: VideoShowEpisodeApiParams::applyCustomFilterByAsset($apiParams, $asset),
            customFilters: [new VideoShowEpisodeFilter()],
        ));
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(VideoShowEpisode::class), OAResponse(VideoShowEpisode::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] VideoShowEpisode $videoShowEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_CREATE, $videoShowEpisode);
        if ($videoShowEpisode->getAsset()) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $videoShowEpisode->getAsset());
        }
        $episode = $this->videoShowEpisodeFacade->create($videoShowEpisode);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $episode);

        return $this->createdResponse($episode);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{videoShowEpisode}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('VideoShowEpisode'), OARequest(VideoShowEpisode::class), OAResponse(VideoShowEpisode::class), OAResponseValidation]
    public function update(Request $request, VideoShowEpisode $videoShowEpisode, #[SerializeParam] VideoShowEpisode $newVideoShowEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_UPDATE, $videoShowEpisode);
        if ($videoShowEpisode->getAsset()) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $videoShowEpisode->getAsset());
        }
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoShowEpisode);

        return $this->okResponse(
            $this->videoShowEpisodeFacade->update($videoShowEpisode, $newVideoShowEpisode)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{videoShowEpisode}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('VideoShowEpisode'), OAResponseDeleted]
    public function delete(Request $request, VideoShowEpisode $videoShowEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_SHOW_EPISODE_DELETE, $videoShowEpisode);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoShowEpisode);
        $this->videoShowEpisodeFacade->delete($videoShowEpisode);

        return $this->noContentResponse();
    }
}
