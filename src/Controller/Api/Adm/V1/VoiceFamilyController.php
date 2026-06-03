<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseDeleted;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\ApiFilter\ExySystemApiParams;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\VoiceFamilyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\CustomFilter\CustomExtSystemFilter;
use AnzuSystems\CoreDamBundle\Repository\VoiceFamilyRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/voice-family', name: 'adm_voice_family_v1_')]
#[OA\Tag('VoiceFamily')]
final class VoiceFamilyController extends AbstractApiController
{
    public function __construct(
        private readonly VoiceFamilyFacade $voiceFamilyFacade,
        private readonly VoiceFamilyRepository $voiceFamilyRepository,
    ) {
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route('/ext-system/{extSystem}', name: 'get_list_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('extSystem'), OAResponseList(VoiceFamily::class)]
    public function getListByExtSystem(ApiParams $apiParams, ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_READ, $extSystem);

        return $this->okResponse(
            $this->voiceFamilyRepository->findByApiParams(
                apiParams: ExySystemApiParams::applyCustomFilter($apiParams, $extSystem),
                customFilters: [new CustomExtSystemFilter()],
            ),
        );
    }

    /**
     * Lists the voice families of the ext system the given asset licence belongs to. Called by the CMS,
     * which knows the asset licence (not the DAM ext system) of the article being narrated.
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route('/licence/{assetLicence}', name: 'get_list_by_asset_licence', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetLicence'), OAResponseList(VoiceFamily::class)]
    public function getListByLicence(ApiParams $apiParams, AssetLicence $assetLicence): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_READ, $assetLicence);

        return $this->okResponse(
            $this->voiceFamilyRepository->findByApiParams(
                apiParams: ExySystemApiParams::applyAssetLicenceExtSystemCustomFilter($apiParams, $assetLicence),
                customFilters: [new CustomExtSystemFilter()],
            ),
        );
    }

    #[Route('/{voiceFamily}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('voiceFamily'), OAResponse(VoiceFamily::class)]
    public function getOne(VoiceFamily $voiceFamily): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_READ, $voiceFamily);

        return $this->okResponse($voiceFamily);
    }

    /**
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(VoiceFamily::class), OAResponse(VoiceFamily::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] VoiceFamily $voiceFamily): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_CREATE, $voiceFamily);

        $this->voiceFamilyFacade->create($voiceFamily);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voiceFamily);

        return $this->createdResponse($voiceFamily);
    }

    /**
     * Slug + extSystem are immutable post-create.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{voiceFamily}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('voiceFamily'), OARequest(VoiceFamily::class), OAResponse(VoiceFamily::class), OAResponseValidation]
    public function update(Request $request, VoiceFamily $voiceFamily, #[SerializeParam] VoiceFamily $newVoiceFamily): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_UPDATE, $voiceFamily);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voiceFamily);

        return $this->okResponse(
            $this->voiceFamilyFacade->update($voiceFamily, $newVoiceFamily),
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route('/{voiceFamily}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('voiceFamily'), OAResponseDeleted]
    public function delete(Request $request, VoiceFamily $voiceFamily): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_VOICE_FAMILY_DELETE, $voiceFamily);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $voiceFamily);
        $this->voiceFamilyFacade->delete($voiceFamily);

        return $this->noContentResponse();
    }
}
