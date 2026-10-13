<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysDetailDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\VideoFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag('Video')]
#[Route('/video', 'sys_video_')]
final class VideoController extends AbstractApiController
{
    public function __construct(
        private readonly AssetSysFacade $assetSysFacade
    ) {
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     * @throws AssetFileProcessFailed
     */
    #[Route('/from-url', 'create_from_url', methods: [Request::METHOD_POST])]
    #[OARequest(VideoFileSysUrlCreateDto::class), OAResponse(AssetFileSysDetailDecorator::class), OAResponseValidation, OAResponseCreated]
    public function createFromUrl(Request $request, #[SerializeParam] VideoFileSysUrlCreateDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $dto);
        $videoFile = $this->assetSysFacade->createVideoFromUrlDto($dto);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $videoFile);

        return $this->okResponse(AssetFileSysDetailDecorator::getInstance($videoFile));
    }
}
