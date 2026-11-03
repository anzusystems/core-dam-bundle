<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageReleaseFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageTransferFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUseFacade;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\ImageUsageConflictException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageReleaseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageTransferResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageUseResultListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyRequestItemDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest as OADamRequest;
use AnzuSystems\CoreDamBundle\Repository\AssetRepository;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
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
        private readonly ImageUseFacade $imageUseFacade,
        private readonly ImageReleaseFacade $imageReleaseFacade,
        private readonly ImageTransferFacade $imageTransferFacade,
        private readonly AssetRepository $assetRepository,
        private readonly Validator $validator,
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
        $this->validator->validate($dto);
        $this->warmUpAccessCheck(array_map(
            static fn (ImageUseItemDto $item): ImageFile => $item->getImageFile(),
            $dto->getItems()->toArray(),
        ));
        foreach ($dto->getItems() as $item) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $item->getImageFile()->getAsset());
            $targetLicence = $item->getTargetAssetLicence();
            if ($targetLicence instanceof AssetLicence && $targetLicence->isNot($item->getImageFile()->getLicence())) {
                $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $targetLicence);
            }
        }
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
        $this->warmUpAccessCheck($dto->getImageFileIds()->toArray());
        foreach ($dto->getImageFileIds() as $imageFile) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $imageFile->getAsset());
        }
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: AssetFile::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($dto->getImageFileIds(), static fn (AssetFile $imageFile): string => (string) $imageFile->getId()),
        );
        $this->imageReleaseFacade->releaseImages($dto);

        return $this->noContentResponse();
    }

    /**
     * Hands photos the caller already uses from one of its holders to another, on the whole take-over group.
     * Never refuses a photo: one held by somebody else than the sender is left alone and listed in the result.
     *
     * @throws AppReadOnlyModeException
     * @throws Throwable
     */
    #[Route(
        path: '/transfer',
        name: 'transfer',
        methods: [Request::METHOD_POST],
    )]
    #[OADamRequest(ImageTransferRequestDto::class), OAResponse(ImageTransferResultDto::class), OAResponseValidation]
    public function transfer(Request $request, #[SerializeParam] ImageTransferRequestDto $dto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->warmUpAccessCheck($dto->getImageFileIds()->toArray());
        foreach ($dto->getImageFileIds() as $imageFile) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $imageFile->getAsset());
        }
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: AssetFile::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($dto->getImageFileIds(), static fn (AssetFile $imageFile): string => (string) $imageFile->getId()),
        );

        return $this->okResponse(
            $this->imageTransferFacade->transferImages($dto)
        );
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
        $this->validator->validate($copyDto);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $copyDto->getTargetAssetLicence());
        $this->warmUpAccessCheck(array_map(
            static fn (JobImageCopyRequestItemDto $item): ImageFile => $item->getImageFile(),
            $copyDto->getItems()->toArray(),
        ));
        foreach ($copyDto->getItems() as $item) {
            $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $item->getImageFile()->getAsset());
        }

        return $this->okResponse(
            $this->imageCopyFacade->createFromCopyList($copyDto)
        );
    }

    /**
     * The voter runs once per photo and reads the user's EXTRA_LAZY licence collections and the lazy asset of
     * every call, so a batch of 1 000 photos would otherwise cost thousands of queries.
     *
     * @param list<ImageFile> $imageFiles
     */
    private function warmUpAccessCheck(array $imageFiles): void
    {
        $this->getUser()->warmUpLicenceAccess();

        $assetIds = [];
        foreach ($imageFiles as $imageFile) {
            $assetIds[(string) $imageFile->getAsset()->getId()] = true;
        }
        if ([] !== $assetIds) {
            $this->assetRepository->findByIds(array_keys($assetIds));
        }
    }
}
