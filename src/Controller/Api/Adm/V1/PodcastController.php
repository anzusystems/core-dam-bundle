<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseInfiniteList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\ApiFilter\LicensedEntityApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Podcast\PodcastFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Podcast;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\LicensedEntityFilter;
use AnzuSystems\CoreDamBundle\Repository\PodcastRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_READ, $podcast);

        return $this->okResponse($podcast);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(Podcast::class), OAResponse(Podcast::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] Podcast $podcast): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_CREATE, $podcast);
        $podcast = $this->podcastFacade->create($podcast);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $podcast);

        return $this->createdResponse($podcast);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{podcast}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('podcast'), OARequest(Podcast::class), OAResponse(Podcast::class), OAResponseValidation]
    public function update(Request $request, Podcast $podcast, #[SerializeParam] Podcast $newPodcast): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_UPDATE, $podcast);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $podcast);

        return $this->okResponse(
            $this->podcastFacade->update($podcast, $newPodcast)
        );
    }

    /**
     * @throws ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(Podcast::class)]
    public function getListByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_READ, $extSystem);

        return $this->okResponse($this->podcastRepository->findByApiParamsWithInfiniteListing(
            apiParams: LicensedEntityApiParams::applyCustomFilter($apiParams, $extSystem),
            customFilters: [new LicensedEntityFilter()]
        ));
    }

    /**
     * @throws ORMException
     */
    #[Route('/licence/{assetLicence}', name: 'get_list_by_asset_licence', methods: [Request::METHOD_GET])]
    #[OAResponseInfiniteList(Podcast::class)]
    public function getList(ApiParams $apiParams, AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_PODCAST_READ, $assetLicence);

        return $this->okResponse($this->podcastRepository->findByApiParamsWithInfiniteListing(
            apiParams: LicensedEntityApiParams::applyLicenceCustomFilter($apiParams, $assetLicence),
            customFilters: [new LicensedEntityFilter()]
        ));
    }
}
