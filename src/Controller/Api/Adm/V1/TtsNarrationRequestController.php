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
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\DispatchStatus;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\TtsNarrationRequestRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tts-narration-request', name: 'adm_tts_narration_request_v1_')]
#[OA\Tag('TtsNarrationRequest')]
final class TtsNarrationRequestController extends AbstractApiController
{
    public function __construct(
        private readonly TtsDispatchFacade $dispatchNew,
        private readonly TtsCancellationFacade $cancelRequest,
        private readonly TtsNarrationRequestRepositoryDecorator $requestDecorator,
    ) {
    }

    /**
     * Lists narration requests for a stable asset (CMS polls article narration progress); authorized on the asset.
     */
    #[Route('/asset/{asset}', name: 'get_list_by_asset', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponseList(TtsNarrationRequest::class)]
    public function getListByAsset(ApiParams $apiParams, Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_READ, $asset);

        return $this->okResponse($this->requestDecorator->findByAsset($apiParams, $asset));
    }

    #[Route('/{narrationRequest}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('narrationRequest'), OAResponse(TtsNarrationRequest::class)]
    public function getOne(TtsNarrationRequest $narrationRequest): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_READ, $narrationRequest);

        return $this->okResponse($this->requestDecorator->getDetail($narrationRequest));
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_SYNTHESIZE, $dto);

        $result = $this->dispatchNew->synthesize($dto);

        if (DispatchStatus::Pending === $result->status && null !== $result->narrationRequest) {
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_CANCEL, $request);

        $cancelled = $this->cancelRequest->cancel($request, (string) $this->getUser()->getId());

        if (false === $cancelled) {
            return new JsonResponse(null, Response::HTTP_CONFLICT);
        }

        return $this->okResponse($request);
    }
}
