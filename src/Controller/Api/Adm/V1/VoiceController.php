<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFacade;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Repository\Decorator\VoiceRepositoryDecorator;
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
        private readonly VoiceRepositoryDecorator $voiceDecorator,
    ) {
    }

    #[Route('/voice-family/{voiceFamily}', name: 'get_list_by_family', methods: [Request::METHOD_GET])]
    #[OAParameterPath('voiceFamily'), OAResponseList(Voice::class)]
    public function getListByFamily(VoiceFamily $voiceFamily): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_READ, $voiceFamily);

        return $this->okResponse($this->voiceDecorator->findByFamily($voiceFamily));
    }

    #[Route('/{voice}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('voice'), OAResponse(Voice::class)]
    public function getOne(Voice $voice): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_READ, $voice);

        return $this->okResponse($voice);
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
