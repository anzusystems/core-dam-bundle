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
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsCancellationFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\DispatchKind;
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

#[Route(path: '/tts-narration-request', name: 'adm_tts_narration_request_v1_')]
#[OA\Tag('TtsNarrationRequest')]
final class TtsNarrationRequestController extends AbstractApiController
{
    public function __construct(
        private readonly TtsDispatchFacade $dispatchNew,
        private readonly TtsCancellationFacade $cancelRequest,
        private readonly TtsNarrationRequestRepository $requestRepo,
        private readonly AssetLicenceRepository $licenceRepo,
        private readonly TtsAssetRepository $ttsAssetRepo,
    ) {
    }

    #[Route('', name: 'get_list', methods: [Request::METHOD_GET])]
    #[OAResponseList(TtsNarrationRequest::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_LIST);

        return $this->okResponse(
            $this->requestRepo->findByApiParams($apiParams),
        );
    }

    #[Route('/{narrationRequest}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('narrationRequest'), OAResponse(TtsNarrationRequest::class)]
    public function getOne(TtsNarrationRequest $narrationRequest): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_READ);

        $resultAssetId = $narrationRequest->getResultAssetId();
        $ttsAsset = null !== $resultAssetId
            ? $this->ttsAssetRepo->findByAssetIdJoined($resultAssetId)
            : null;

        $narrationRequest->setTtsAsset($ttsAsset);

        return $this->okResponse($narrationRequest);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/synthesize', name: 'synthesize', methods: [Request::METHOD_POST])]
    #[OARequest(TtsSynthesizeRequestDto::class), OAResponseValidation]
    public function synthesize(#[SerializeParam] TtsSynthesizeRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_SYNTHESIZE, $dto->resolveAssetLicence());

        $result = $this->dispatchNew->execute($dto);

        if (DispatchKind::Pending === $result->kind && null !== $result->narrationRequest) {
            return $this->createdResponse($result->narrationRequest);
        }

        return new JsonResponse(null, Response::HTTP_CONFLICT);
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ImmutableAudioNarrationException
     */
    #[Route('/{request}/cancel', name: 'cancel', methods: [Request::METHOD_POST])]
    #[OAParameterPath('request'), OAResponse(TtsNarrationRequest::class)]
    public function cancel(TtsNarrationRequest $request): JsonResponse
    {
        App::throwOnReadOnlyMode();

        $licence = $this->licenceRepo->find($request->getAssetLicenceId())
            ?? throw new NotFoundHttpException(sprintf('AssetLicence for request "%s" not found.', (string) $request->getId()));
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_CANCEL, $licence);

        $cancelled = $this->cancelRequest->execute($request, (string) $this->getUser()->getId());

        if (false === $cancelled) {
            return new JsonResponse(null, Response::HTTP_CONFLICT);
        }

        return $this->okResponse($request);
    }
}
