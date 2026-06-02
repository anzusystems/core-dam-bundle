<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFacade;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFactory;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\VoiceRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/voice', name: 'adm_voice_v1_')]
#[OA\Tag('Voice')]
final class VoiceController extends AbstractApiController
{
    public function __construct(
        private readonly VoiceFacade $voiceFacade,
        private readonly VoiceRepository $voiceRepository,
        private readonly VoiceFactory $voiceFactory,
    ) {
    }

    #[Route('/voice-family/{voiceFamily}', name: 'get_list_by_family', methods: [Request::METHOD_GET])]
    #[OAParameterPath('voiceFamily'), OAResponseList(Voice::class)]
    public function getListByFamily(VoiceFamily $voiceFamily): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_READ, $voiceFamily);

        $voices = $this->voiceRepository->findAllByFamily($voiceFamily);

        return $this->okResponse(
            (new ApiResponseList())->setData($voices)->setTotalCount(count($voices)),
        );
    }

    #[Route('/{voice}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('voice'), OAResponse(Voice::class)]
    public function getOne(Voice $voice): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_READ, $voice);

        return $this->okResponse($voice);
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(Voice::class), OAResponse(Voice::class), OAResponseValidation]
    public function create(Request $request): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $voice = $this->voiceFactory->fromJson((string) $request->getContent());
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
    #[OAParameterPath('voice'), OARequest(Voice::class), OAResponse(Voice::class), OAResponseValidation]
    public function update(Request $request, Voice $voice): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_UPDATE, $voice);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voice);

        $newVoice = $this->voiceFactory->fromJson((string) $request->getContent());

        return $this->okResponse(
            $this->voiceFacade->update($voice, $newVoice),
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route('/{voice}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('voice'), OAResponseDeleted]
    public function delete(Request $request, Voice $voice): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_DELETE, $voice);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voice);
        $this->voiceFacade->delete($voice);

        return $this->noContentResponse();
    }
}
