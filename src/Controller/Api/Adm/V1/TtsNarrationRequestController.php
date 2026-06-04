<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsCancellationFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
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
        private readonly Validator $validator,
    ) {
    }

    /**
     * Lists narration requests of an asset licence; authorized on the licence. The CMS polls its article's
     * narration progress here, narrowing to the article's asset via the `asset` api-params filter.
     */
    #[Route('/licence/{assetLicence}', name: 'get_list_by_licence', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetLicence'), OAResponseList(TtsNarrationRequest::class)]
    public function getListByLicence(ApiParams $apiParams, AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_READ, $assetLicence);

        return $this->okResponse($this->requestDecorator->findByLicence($apiParams, $assetLicence));
    }

    /**
     * Lists narration requests of an ext system (DAM admin overview); authorized on the ext system.
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('extSystem'), OAResponseList(TtsNarrationRequest::class)]
    public function getListByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_READ, $extSystem);

        return $this->okResponse($this->requestDecorator->findByExtSystem($apiParams, $extSystem));
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
        // Before ACL: voter reads the licence off the DTO → malformed must be 422 here, not a 500.
        $this->validator->validate($dto);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_NARRATION_REQUEST_SYNTHESIZE, $dto);

        $result = $this->dispatchNew->synthesize($dto);

        // Per DispatchResult contract: Duplicate → 200 hands back the deduped asset id (don't drop it).
        return $this->getResponse(
            SynthesizeResponseDto::fromResult($result),
            match ($result->status) {
                DispatchStatus::AlreadyPending => Response::HTTP_CONFLICT,
                DispatchStatus::Pending => Response::HTTP_CREATED,
                default => Response::HTTP_OK,
            },
        );
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
            return $this->conflictResponse();
        }

        return $this->okResponse($request);
    }
}
