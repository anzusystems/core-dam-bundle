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
use AnzuSystems\CoreDamBundle\ApiFilter\PodcastApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\PodcastFilter;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/podcast', name: 'adm_podcast_v1_')]
#[OA\Tag('Podcast')]
final class PodcastController extends AbstractApiController
{
    public function __construct(
        private readonly PodcastFacade $podcastFacade,
        private readonly PodcastRepository $podcastRepository,
    ) {
    }

    #[Route('/{podcast}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('podcast'), OAResponse(Podcast::class)]
    public function getOne(Podcast $podcast): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_VIEW, $podcast);

        return $this->okResponse($podcast);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('podcast', converter: SerializerParamConverter::class)]
    #[OARequest(Podcast::class), OAResponse(Podcast::class), OAResponseValidation]
    public function create(Podcast $podcast): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_CREATE, $podcast);

        return $this->createdResponse(
            $this->podcastFacade->create($podcast)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{podcast}', name: 'update', methods: [Request::METHOD_PUT])]
    #[ParamConverter('newPodcast', converter: SerializerParamConverter::class)]
    #[OAParameterPath('podcast'), OARequest(Podcast::class), OAResponse(Podcast::class), OAResponseValidation]
    public function update(Podcast $podcast, Podcast $newPodcast): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_UPDATE, $podcast);

        return $this->okResponse(
            $this->podcastFacade->update($podcast, $newPodcast)
        );
    }

    /**
     * @throws ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list_by_ext_system', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([Podcast::class])]
    public function getListByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_VIEW, $extSystem);

        return $this->okResponse($this->podcastRepository->findByApiParamsWithInfiniteListing(
            apiParams: PodcastApiParams::applyCustomFilter($apiParams, $extSystem),
            customFilters: [new PodcastFilter()]
        ));
    }

    /**
     * @throws ORMException
     */
    #[Route('/licence/{assetLicence}', name: 'get_list_by_asset_licence', methods: [Request::METHOD_GET])]
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    #[OAResponse([Podcast::class])]
    public function getList(ApiParams $apiParams, AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_VIEW, $assetLicence);

        return $this->okResponse($this->podcastRepository->findByApiParamsWithInfiniteListing(
            apiParams: PodcastApiParams::applyLicenceCustomFilter($apiParams, $assetLicence),
            customFilters: [new PodcastFilter()]
        ));
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{podcast}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('podcast'), OAResponseDeleted]
    public function delete(Podcast $podcast): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_DELETE, $podcast);

        $this->podcastFacade->delete($podcast);

        return $this->noContentResponse();
    }
}
