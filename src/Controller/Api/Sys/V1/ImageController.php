<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade;
use AnzuSystems\CoreDamBundle\Domain\Job\JobImageCopyFacade;
use AnzuSystems\CoreDamBundle\Entity\JobImageCopy;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Attributes\SerializeIterableParam;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\AssetFileCopyResultDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Job\JobImageCopyItemDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest as OADamRequest;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\Common\Collections\Collection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[OA\Tag('Image')]
#[Route('/image', 'sys_image_')]
final class ImageController extends AbstractApiController
{
    public function __construct(
        private readonly JobImageCopyFacade $imageCopyFacade,
    ) {
    }

    /**
     * @param Collection<int, JobImageCopyItemDto> $copyList
     * @throws Throwable
     *
     * @throws ForbiddenOperationException
     */
    #[Route(
        path: '/copy-to-licence',
        name: 'copy_image',
        methods: [Request::METHOD_PATCH]
    )]
    #[OADamRequest(JobImageCopyDto::class), OAResponse(JobImageCopy::class), OAResponseValidation]
    public function createCopyJob(#[SerializeParam] JobImageCopyDto $copyDto): JsonResponse
    {
        return $this->okResponse(
            $this->imageCopyFacade->createFromCopyList($copyDto)
        );
    }
}
