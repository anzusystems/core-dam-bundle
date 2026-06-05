<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsDispatchFacade;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Enum\DispatchStatus;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/audio/narration', name: 'sys_tts_narration_request_v1_')]
#[OA\Tag('TtsNarrationRequest')]
final class TtsNarrationRequestController extends AbstractApiController
{
    public function __construct(
        private readonly TtsDispatchFacade $dispatchNew,
    ) {
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route(path: '', name: 'dispatch', methods: [Request::METHOD_POST])]
    #[OARequest(TtsSynthesizeRequestDto::class), OAResponseValidation]
    public function dispatch(#[SerializeParam] TtsSynthesizeRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        // Sys auth is role-gated (ROLE_SYS_SYNTHETIZE via security access_control), no per-permission ACL here.
        $result = $this->dispatchNew->synthesize($dto);

        return $this->getResponse(
            SynthesizeResponseDto::fromResult($result),
            match ($result->status) {
                DispatchStatus::AlreadyPending => Response::HTTP_CONFLICT,
                DispatchStatus::Pending => Response::HTTP_ACCEPTED,
                default => Response::HTTP_OK,
            },
        );
    }
}
