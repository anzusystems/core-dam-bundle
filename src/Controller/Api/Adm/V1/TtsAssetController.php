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
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\RegenerateTts;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\UnpublishTtsAsset;
use AnzuSystems\CoreDamBundle\Domain\Tts\Command\UpdatePodcastMembership;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Exception\ImmutableAudioNarrationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\SynthesizeResponseDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsPodcastsUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsReasonRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsRegenerateRequestDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\PodcastEpisodeRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;
use AnzuSystems\CoreDamBundle\Repository\TtsNarrationRequestRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tts-asset', name: 'adm_tts_asset_v1_')]
#[OA\Tag('TtsAsset')]
final class TtsAssetController extends AbstractApiController
{
    public function __construct(
        private readonly RegenerateTts $regenerateTts,
        private readonly UnpublishTtsAsset $unpublishTtsAsset,
        private readonly UpdatePodcastMembership $updatePodcastMembership,
        private readonly TtsAssetRepository $ttsAssetRepo,
        private readonly TtsNarrationRequestRepository $ttsRequestRepo,
        private readonly PodcastEpisodeRepository $episodeRepo,
    ) {
    }

    #[Route('/{asset}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponse(TtsAudioAdmDetailDto::class)]
    public function getOne(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_READ, $asset);

        return $this->okResponse(TtsAudioAdmDetailDto::getInstance(
            $asset,
            $this->ttsAssetRepo->findByAsset($asset),
            $this->ttsRequestRepo->findLastIdByAsset((string) $asset->getId()),
            $this->episodeRepo->findPodcastIdsByAsset($asset),
        ));
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ImmutableAudioNarrationException
     */
    #[Route('/{asset}/regenerate', name: 'regenerate', methods: [Request::METHOD_POST])]
    #[OAParameterPath('asset'), OARequest(TtsRegenerateRequestDto::class), OAResponseValidation]
    public function regenerate(Request $request, Asset $asset, #[SerializeParam] TtsRegenerateRequestDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_REGENERATE, $asset);
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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_UNPUBLISH, $asset);
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
    #[Route('/{asset}/podcasts', name: 'update_podcasts', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('asset'), OARequest(TtsPodcastsUpdateDto::class), OAResponseValidation]
    public function updatePodcasts(Request $request, Asset $asset, #[SerializeParam] TtsPodcastsUpdateDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_UPDATE_PODCASTS, $asset);
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        $this->updatePodcastMembership->execute($asset, $dto->getPodcasts());

        return $this->noContentResponse();
    }
}
