<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\CancelRequest;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\DispatchNewAudioNarration;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\CancelRequestResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchKind;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsReasonRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\AssetLicenceRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tts-narration-request', name: 'adm_tts_narration_request_v1_')]
#[OA\Tag('TtsNarrationRequest')]
final class TtsNarrationRequestController extends AbstractApiController
{
    public function __construct(
        private readonly DispatchNewAudioNarration $dispatchNew,
        private readonly CancelRequest $cancelRequest,
        private readonly TtsNarrationRequestRepository $requestRepo,
        private readonly AssetLicenceRepository $licenceRepo,
    ) {
    }

    /**
     * List TTS narration requests. Standard ApiParams filters work out of the box:
     *   - filter_eq[status]            → TtsRequestStatus enum
     *   - filter_eq[mode]              → TtsRequestMode enum
     *   - filter_eq[voiceFamilySlug]   → VoiceFamily slug
     *   - filter_gte[startedAt] / filter_lte[startedAt] → startedAt range
     */
    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseList(TtsNarrationRequest::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_LIST);

        return $this->okResponse(
            $this->requestRepo->findByApiParams($apiParams),
        );
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_SYNTHESIZE, $licence);
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
    #[Route('/{narrationRequest}/cancel', name: 'cancel', methods: [Request::METHOD_POST])]
    #[OAParameterPath('narrationRequest'), OARequest(TtsReasonRequestDto::class), OAResponse(CancelRequestResponseDto::class), OAResponseValidation]
    public function cancel(TtsNarrationRequest $narrationRequest, #[SerializeParam] TtsReasonRequestDto $dto): JsonResponse
    {
        $licence = null !== $narrationRequest->getAssetLicenceId() ? $this->licenceRepo->find($narrationRequest->getAssetLicenceId()) : null;
        if (null === $licence) {
            throw new NotFoundHttpException(sprintf('AssetLicence for request "%s" not found.', (string) $narrationRequest->getId()));
        }
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_CANCEL, $licence);
        App::throwOnReadOnlyMode();

        $result = $this->cancelRequest->execute(
            requestId: (string) $narrationRequest->getId(),
            reason: $dto->getReason(),
            userId: (string) $this->getUser()->getId(),
        );

        return $this->okResponse($result);
    }
}
