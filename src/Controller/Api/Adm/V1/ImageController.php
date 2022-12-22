<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFilePositionFacade;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Exception\AssetSlotUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidExtSystemConfigurationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\ChunkParamConverter;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/image', name: 'adm_image_v1_')]
#[OA\Tag('Image')]
final class ImageController extends AbstractApiController
{
    public function __construct(
        private readonly ImageFacade $imageFacade,
        private readonly ImageStatusFacade $statusFacade,
        private readonly ChunkFacade $chunkFacade,
        private readonly AssetFileDownloadFacade $assetFileDownloadFacade,
        private readonly AssetFilePositionFacade $assetFilePositionFacade,
    ) {
    }

    /**
     * Upload an image with specific licence from external provider
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}/external-provider', name: 'upload_from_external_provider', methods: [Request::METHOD_POST])]
    #[ParamConverter('uploadDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(UploadAssetFromExternalProviderDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function uploadFromExternalProvider(UploadAssetFromExternalProviderDto $uploadDto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $uploadDto->getExternalProvider());
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_CREATE, $assetLicence);

        return $this->createdResponse(
            ImageFileAdmDetailDto::getInstance(
                $this->imageFacade->createAssetFilesFromExternalProvider($uploadDto, $assetLicence)
            )
        );
    }

    /**
     * Create an image with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}', name: 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('image', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(ImageAdmCreateDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function create(ImageAdmCreateDto $image, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_CREATE, $assetLicence);

        return $this->createdResponse(
            ImageFileAdmDetailDto::getInstance($this->imageFacade->createAssetFile($image, $assetLicence))
        );
    }

    /**
     * Create image for asset and assign to specific position.
     *
     * @throws ValidationException
     * @throws ForbiddenOperationException
     * @throws InvalidExtSystemConfigurationException
     * @throws AssetSlotUsedException
     * @throws AppReadOnlyModeException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/asset/{asset}/slot-name/{slotName}', name: 'create_to_asset', methods: [Request::METHOD_POST])]
    #[ParamConverter('image', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(ImageAdmCreateDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function createToAsset(Asset $asset, ImageAdmCreateDto $image, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_CREATE, $asset);

        return $this->createdResponse(
            ImageFileAdmDetailDto::getInstance($this->imageFacade->addAssetFileToAsset($asset, $image, $slotName))
        );
    }

    /**
     * Create image for asset and assign to specific position.
     *
     * @throws ValidationException
     * @throws ForbiddenOperationException
     * @throws InvalidExtSystemConfigurationException
     * @throws AssetSlotUsedException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{image}/asset/{asset}/slot-name/{slotName}', name: 'set_to_position', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('assetLicence'), OARequest(ImageAdmCreateDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function setToPosition(Asset $asset, ImageFile $image, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_UPDATE, $asset);

        return $this->okResponse(
            ImageFileAdmDetailDto::getInstance($this->assetFilePositionFacade->setToPosition($asset, $image, $slotName))
        );
    }

    /**
     * Add chunk to ImageFile
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{image}/chunk', name: 'add_chunk', methods: [Request::METHOD_POST])]
    #[ParamConverter('chunk', converter: ChunkParamConverter::class)]
    #[OAParameterPath('image'), OARequest(ChunkAdmCreateDto::class), OAResponse(Chunk::class), OAResponseValidation]
    public function addChunk(ImageFile $image, ChunkAdmCreateDto $chunk): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_CREATE, $image);

        return $this->createdResponse(
            $this->chunkFacade->create($chunk, $image)
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/{image}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(ImageFileAdmDetailDto::class)]
    public function getOne(ImageFile $image): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_VIEW, $image);

        return $this->okResponse(ImageFileAdmDetailDto::getInstance($image));
    }

    /**
     * Finish upload and start postprocess
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{image}/uploaded', name: 'finish_upload', methods: [Request::METHOD_PATCH])]
    #[ParamConverter('assetFinishDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('image'), OARequest(AssetAdmFinishDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function finishUpload(AssetAdmFinishDto $assetFinishDto, ImageFile $image): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_CREATE, $image);

        return $this->okResponse(
            ImageFileAdmDetailDto::getInstance(
                $this->statusFacade->finishUpload($assetFinishDto, $image)
            )
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{image}/rotate/{angle}', name: 'rotate', requirements: ['angle' => '(90)|(180)|(270)'], methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('image'), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function rotate(ImageFile $image, float $angle): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_UPDATE, $image);

        return $this->okResponse(
            ImageFileAdmDetailDto::getInstance(
                $this->imageFacade->rotateImage($image, $angle)
            )
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{image}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('image'), OAResponseValidation]
    public function delete(ImageFile $image): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_DELETE, $image);
        $this->imageFacade->delete($image);

        return $this->noContentResponse();
    }

    #[Route(path: '/{image}/download-link', name: 'download_link', methods: [Request::METHOD_GET])]
    #[OAParameterPath('image'), OAResponseValidation]
    public function generateDownloadUrl(ImageFile $image): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_IMAGE_VIEW, $image);

        return $this->okResponse(
            $this->assetFileDownloadFacade->decorateDownloadLink($image)
        );
    }
}
