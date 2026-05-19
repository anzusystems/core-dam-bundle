<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\CancelRequest;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\DispatchNewAudioNarration;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\RegenerateTts;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\ToggleRecommendedPodcast;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\UnpublishTtsAsset;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchKind;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsReasonRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsRecommendedPodcastUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsRegenerateRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tts-audio', name: 'adm_tts_audio_v1_')]
#[OA\Tag('TtsAudio')]
final class TtsAudioController extends AbstractApiController
{
    public function __construct(
        private readonly DispatchNewAudioNarration $dispatchNew,
        private readonly RegenerateTts $regenerateTts,
        private readonly CancelRequest $cancelRequest,
        private readonly UnpublishTtsAsset $unpublishTtsAsset,
        private readonly ToggleRecommendedPodcast $toggleRecommendedPodcast,
        private readonly TtsAssetRepository $ttsAssetRepo,
        private readonly AssetLicenceRepository $licenceRepo,
        private readonly TtsNarrationRequestRepository $requestRepo,
    ) {
    }

    /**
     * List TTS narration requests. Standard ApiParams filters work out of the box:
     *   - filter_eq[status]            → TtsRequestStatus enum
     *   - filter_eq[mode]              → TtsRequestMode enum
     *   - filter_eq[voiceFamilySlug]   → VoiceFamily slug
     *   - filter_gte[startedAt] / filter_lte[startedAt] → startedAt range
     */
    #[Route('/requests', name: 'get_requests_list', methods: [Request::METHOD_GET])]
    #[OAResponseList(TtsNarrationRequest::class)]
    public function getRequestsList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_LIST_REQUESTS);

        return $this->okResponse(
            $this->requestRepo->findByApiParams($apiParams),
        );
    }

    #[Route('/{asset}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponse(TtsAudioAdmDetailDto::class)]
    public function getOne(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_READ, $asset);

        return $this->okResponse(TtsAudioAdmDetailDto::getInstance($asset, $this->ttsAssetRepo->findByAsset($asset)));
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ImmutableAudioNarrationException
     */
    #[Route('/{asset}/regenerate', name: 'regenerate', methods: [Request::METHOD_POST])]
    #[OAParameterPath('asset'), OARequest(TtsRegenerateRequestDto::class), OAResponseValidation]
    public function regenerate(Request $request, Asset $asset, #[SerializeParam] TtsRegenerateRequestDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_REGENERATE, $asset);
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        $narrationRequest = $this->regenerateTts->execute(
            stableAssetId: (string) $asset->getId(),
            voiceFamilySlug: $dto->getVoiceFamilySlug(),
        );

        return $this->getResponse(
            SynthesizeResponseDto::fromRequestId((string) $narrationRequest->getId()),
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{asset}', name: 'unpublish', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('asset'), OARequest(TtsReasonRequestDto::class), OAResponseValidation]
    public function unpublish(Request $request, Asset $asset, #[SerializeParam] TtsReasonRequestDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_UNPUBLISH, $asset);
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        $this->unpublishTtsAsset->execute(
            asset: $asset,
            reason: $dto->getReason(),
            userId: (string) $this->getUser()->getId(),
        );

        return $this->noContentResponse();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route('/{asset}/recommended-podcast', name: 'recommended_podcast', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('asset'), OARequest(TtsRecommendedPodcastUpdateDto::class), OAResponseValidation]
    public function recommendedPodcast(Request $request, Asset $asset, #[SerializeParam] TtsRecommendedPodcastUpdateDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_TOGGLE_RECOMMENDED_PODCAST, $asset);
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        $newValue = $this->toggleRecommendedPodcast->execute($asset, $dto->isInclude());

        return $this->okResponse(['includeInRecommendedPodcast' => $newValue]);
    }

    /**
     * Returns 202 Accepted on new dispatch or 200 with {status: already_exists|already_pending} on idempotent hit.
     *
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/synthesize', name: 'synthesize', methods: [Request::METHOD_POST])]
    #[OARequest(TtsSynthesizeRequestDto::class), OAResponseValidation]
    public function synthesize(#[SerializeParam] TtsSynthesizeRequestDto $dto): JsonResponse
    {
        $licence = $dto->getAssetLicence();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_SYNTHESIZE, $licence);
        App::throwOnReadOnlyMode();

        $result = $this->dispatchNew->execute($dto, $licence);
        $isNewRequest = DispatchKind::Pending === $result->kind;

        return $this->getResponse(
            SynthesizeResponseDto::fromResult($result),
            $isNewRequest ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    /**
     * Cancels any in-flight TTS narration request — `initial` (no asset yet) or `regenerate` (asset
     * in `superseding` state). Dispatch branches on `request.mode` internally.
     *
     * @throws AppReadOnlyModeException
     * @throws ImmutableAudioNarrationException
     */
    #[Route('/request/{narrationRequest}/cancel', name: 'cancel_request', methods: [Request::METHOD_POST])]
    #[OAParameterPath('narrationRequest'), OARequest(TtsReasonRequestDto::class), OAResponse(CancelRequestResponseDto::class), OAResponseValidation]
    public function cancelRequest(TtsNarrationRequest $narrationRequest, #[SerializeParam] TtsReasonRequestDto $dto): JsonResponse
    {
        $licence = null !== $narrationRequest->getAssetLicenceId() ? $this->licenceRepo->find($narrationRequest->getAssetLicenceId()) : null;
        if (null === $licence) {
            throw new NotFoundHttpException(sprintf('AssetLicence for request "%s" not found.', (string) $narrationRequest->getId()));
        }
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_AUDIO_CANCEL_REQUEST, $licence);
        App::throwOnReadOnlyMode();

        $result = $this->cancelRequest->execute(
            requestId: (string) $narrationRequest->getId(),
            reason: $dto->getReason(),
            userId: (string) $this->getUser()->getId(),
        );

        return $this->okResponse($result);
    }
}
