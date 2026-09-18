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
use AnzuSystems\CoreDamBundle\Domain\Image\ImageReleaseFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageReleaseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest as OADamRequest;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[OA\Tag('Image')]
#[Route('/image', 'sys_image_')]
final class ImageController extends AbstractApiController
{
    public function __construct(
        private readonly JobImageCopyFacade $imageCopyFacade,
        private readonly AssetFileFirstUseFacade $firstUseFacade,
        private readonly ImageUseFacade $imageUseFacade,
        private readonly ImageReleaseFacade $imageReleaseFacade,
    ) {
    }

    /**
     * Resolve which image file the caller may use for each item of the batch: the requested one, or the
     * copy taken over into its licence. Finished synchronously, so every returned file is usable the moment
     * it is returned. For a single use photo also claims it for the given holder, on the whole take-over
     * group — all items of the batch in one transaction, all-or-nothing.
     *
     * @throws AppReadOnlyModeException
     * @throws ForbiddenOperationException
     * @throws ImageUsageConflictException
     * @throws Throwable
     */
    #[Route(
        path: '/use',
        name: 'use',
        methods: [Request::METHOD_POST],
    )]
    #[OADamRequest(ImageUseRequestDto::class), OAResponse(ImageUseResultListDto::class), OAResponseValidation]
    public function useImages(Request $request, #[SerializeParam] ImageUseRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: AssetFile::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($dto->getItems(), static fn (ImageUseItemDto $item): string => (string) $item->getImageFile()->getId()),
        );

        return $this->okResponse(
            $this->imageUseFacade->useImages($dto)
        );
    }

    /**
     * Drops the holder's claim on every single use photo of the batch, on the whole take-over group — only
     * where that holder actually holds a given group; every other one is silently skipped.
     *
     * @throws AppReadOnlyModeException
     * @throws Throwable
     */
    #[Route(
        path: '/release',
        name: 'release',
        methods: [Request::METHOD_POST],
    )]
    #[OADamRequest(ImageReleaseRequestDto::class), OAResponse(description: 'Released.', response: JsonResponse::HTTP_NO_CONTENT), OAResponseValidation]
    public function release(Request $request, #[SerializeParam] ImageReleaseRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: AssetFile::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($dto->getImageFileIds(), static fn (AssetFile $imageFile): string => (string) $imageFile->getId()),
        );
        $this->imageReleaseFacade->releaseImages($dto);

        return $this->noContentResponse();
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
        $this->firstUseFacade->recordFromRequest($dto);

        return $this->noContentResponse();
    }
}
