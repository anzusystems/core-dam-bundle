<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\ApiFilter\PodcastEpisodeApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeBodyFacade;
use AnzuSystems\CoreDamBundle\Domain\PodcastEpisode\PodcastEpisodeFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Entity\PodcastEpisode;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\PodcastEpisodeFilter;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/podcast-episode', name: 'adm_podcast_episode_v1_')]
#[OA\Tag('PodcastEpisode')]
final class PodcastEpisodeController extends AbstractApiController
{
    public function __construct(
        private readonly PodcastEpisodeRepository $podcastEpisodeRepository,
        private readonly PodcastEpisodeFacade $podcastEpisodeFacade,
        private readonly PodcastEpisodeBodyFacade $episodeBodyFacade,
    ) {
    }

    #[Route('/{podcastEpisode}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('podcast'), OAResponse(Podcast::class)]
    public function getOne(PodcastEpisode $podcastEpisode): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_VIEW, $podcastEpisode);

        return $this->okResponse($podcastEpisode);
    }

    #[Route('/asset/{asset}/podcast/{podcast}/prepare-payload', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAResponse(PodcastEpisode::class)]
    public function preparePayload(Asset $asset, Podcast $podcast): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_VIEW, $podcast);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $asset);

        return $this->okResponse($this->episodeBodyFacade->preparePayload($asset, $podcast));
    }

    /**
     * @throws ORMException
     */
    #[Route('/podcast/{podcast}', name: 'get_list', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([PodcastEpisode::class])]
    public function getList(Podcast $podcast, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_VIEW, $podcast);

        return $this->okResponse($this->podcastEpisodeRepository->findByApiParamsWithInfiniteListing(
            apiParams: PodcastEpisodeApiParams::applyCustomFilter($apiParams, $podcast),
            customFilters: [new PodcastEpisodeFilter()],
        ));
    }

    /**
     * @throws ORMException
     */
    #[Route('/asset/{asset}', name: 'get_list_by_asset', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([PodcastEpisode::class])]
    public function getListByAsset(Asset $asset, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_VIEW, $asset);

        return $this->okResponse($this->podcastEpisodeRepository->findByApiParamsWithInfiniteListing(
            apiParams: PodcastEpisodeApiParams::applyCustomFilterByAsset($apiParams, $asset),
            customFilters: [new PodcastEpisodeFilter()],
        ));
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('podcastEpisode', converter: SerializerParamConverter::class)]
    #[OARequest(PodcastEpisode::class), OAResponse(PodcastEpisode::class), OAResponseValidation]
    public function create(PodcastEpisode $podcastEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_CREATE, $podcastEpisode);
        if ($podcastEpisode->getAsset()) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $podcastEpisode->getAsset());
        }

        return $this->createdResponse(
            $this->podcastEpisodeFacade->create($podcastEpisode)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{podcastEpisode}', name: 'update', methods: [Request::METHOD_PUT])]
    #[ParamConverter('newPodcastEpisode', converter: SerializerParamConverter::class)]
    #[OAParameterPath('podcastEpisode'), OARequest(PodcastEpisode::class), OAResponse(PodcastEpisode::class), OAResponseValidation]
    public function update(PodcastEpisode $podcastEpisode, PodcastEpisode $newPodcastEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_UPDATE, $podcastEpisode);
        if ($podcastEpisode->getAsset()) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $podcastEpisode->getAsset());
        }

        return $this->okResponse(
            $this->podcastEpisodeFacade->update($podcastEpisode, $newPodcastEpisode)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{podcastEpisode}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('podcastEpisode'), OAResponseDeleted]
    public function delete(PodcastEpisode $podcastEpisode): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_EPISODE_DELETE, $podcastEpisode);

        $this->podcastEpisodeFacade->delete($podcastEpisode);

        return $this->noContentResponse();
    }
}
