<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFirstUseFacade;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest as OADamRequest;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

#[OA\Tag('Image')]
#[Route('/image', 'sys_image_')]
final class ImageController extends AbstractApiController
{
    public function __construct(
        private readonly JobImageCopyFacade $imageCopyFacade,
        private readonly AssetFileFirstUseFacade $firstUseFacade,
    ) {
    }

    /**
     * @throws Throwable
     *
     * @throws ForbiddenOperationException
     */
    #[Route(
        path: '/copy-job',
        name: 'copy_image',
        methods: [Request::METHOD_POST],
    )]
    #[OADamRequest(JobImageCopyRequestDto::class), OAResponse(JobImageCopy::class), OAResponseValidation]
    public function createCopyJob(#[SerializeParam] JobImageCopyRequestDto $copyDto): JsonResponse
    {
        return $this->okResponse(
            $this->imageCopyFacade->createFromCopyList($copyDto)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws ValidationException
     * @throws AccessDeniedException
     */
    #[Route(
        path: '/first-use',
        name: 'first_use',
        methods: [Request::METHOD_POST],
    )]
    #[
        OADamRequest(ImageFirstUseRequestDto::class),
        OAResponse(description: 'Items processed.', response: JsonResponse::HTTP_NO_CONTENT),
        OAResponseValidation,
    ]
    public function firstUse(Request $request, #[SerializeParam] ImageFirstUseRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: AssetFile::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($dto->getItems(), static fn (ImageFirstUseItemDto $item): string => $item->getDamId()),
        );
        $this->firstUseFacade->processBatch($dto);

        return $this->noContentResponse();
    }
}
