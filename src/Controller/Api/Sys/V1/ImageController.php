<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileFirstUseFacade;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseItemDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFirstUseRequestDto;
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
        private readonly AssetFileFirstUseFacade $firstUseFacade,
        private readonly AssetRepository $assetRepository,
        private readonly Validator $validator,
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
        $this->firstUseFacade->processBatch($dto);

        return $this->noContentResponse();
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
