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
use AnzuSystems\CoreDamBundle\Domain\Tts\Facade\TtsUnpublishFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Audio\TtsAudioAdmDetailDto;
use AnzuSystems\CoreDamBundle\Repository\Decorator\TtsAssetRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tts-asset', name: 'adm_tts_asset_v1_')]
#[OA\Tag('TtsAsset')]
final class TtsAssetController extends AbstractApiController
{
    public function __construct(
        private readonly TtsUnpublishFacade $unpublishTtsAsset,
        private readonly TtsAssetRepositoryDecorator $ttsAssetDecorator,
    ) {
    }

    #[Route('/{asset}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponse(TtsAudioAdmDetailDto::class)]
    public function getOne(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_READ, $asset);

        return $this->okResponse($this->ttsAssetDecorator->getDetail($asset));
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     */
    #[Route('/{asset}', name: 'unpublish', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('asset'), OAResponseValidation]
    public function unpublish(Request $request, Asset $asset): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_TTS_ASSET_UNPUBLISH, $asset);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        $this->unpublishTtsAsset->unpublish(
            asset: $asset,
            userId: (string) $this->getUser()->getId(),
        );

        return $this->noContentResponse();
    }
}
