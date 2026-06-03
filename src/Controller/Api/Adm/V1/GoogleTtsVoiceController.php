<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFacade;
use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/voice-google-tts', name: 'adm_voice_google_tts_v1_')]
#[OA\Tag('GoogleTtsVoice')]
final class GoogleTtsVoiceController extends AbstractApiController
{
    public function __construct(
        private readonly VoiceFacade $voiceFacade,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(GoogleTtsVoice::class), OAResponse(GoogleTtsVoice::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] GoogleTtsVoice $voice): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_CREATE, $voice);

        $this->voiceFacade->create($voice);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voice);

        return $this->createdResponse($voice);
    }

    /**
     * VoiceFamily binding + discriminator are immutable post-create.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{voice}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('voice'), OARequest(GoogleTtsVoice::class), OAResponse(GoogleTtsVoice::class), OAResponseValidation]
    public function update(Request $request, GoogleTtsVoice $voice, #[SerializeParam] GoogleTtsVoice $newVoice): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_UPDATE, $voice);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voice);

        return $this->okResponse(
            $this->voiceFacade->update($voice, $newVoice),
        );
    }
}
