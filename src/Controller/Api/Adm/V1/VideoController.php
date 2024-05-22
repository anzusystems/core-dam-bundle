<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetFile\AssetFileDownloadFacade;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoDistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoPositionFacade;
use AnzuSystems\CoreDamBundle\Domain\Video\VideoStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\Distribution;
use AnzuSystems\CoreDamBundle\Entity\VideoFile;
use AnzuSystems\CoreDamBundle\Exception\AssetSlotUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidExtSystemConfigurationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Distribution\DistributionImagePreviewAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\Exception\ORMException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/video', name: 'adm_video_v1_')]
#[OA\Tag('Video')]
final class VideoController extends AbstractApiController
{
    public function __construct(
        private readonly VideoFacade $videoFacade,
        private readonly VideoStatusFacade $statusFacade,
        private readonly ChunkFacade $chunkFacade,
        private readonly AssetFileDownloadFacade $assetFileDownloadFacade,
        private readonly VideoPositionFacade $videoPositionFacade,
        private readonly VideoDistributionFacade $videoDistributionFacade,
    ) {
    }

    /**
     * Upload a video with specific licence from external provider
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}/external-provider', name: 'upload_from_external_provider', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(UploadAssetFromExternalProviderDto::class), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function uploadFromExternalProvider(
        #[SerializeParam]
        UploadAssetFromExternalProviderDto $uploadDto,
        AssetLicence $assetLicence,
    ): JsonResponse {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $uploadDto->getExternalProvider());
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_CREATE, $assetLicence);

        return $this->createdResponse(
            VideoFileAdmDetailDto::getInstance(
                $this->videoFacade->createAssetFilesFromExternalProvider($uploadDto, $assetLicence)
            )
        );
    }

    /**
     * Create an VideoFile with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}', name: 'create', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(VideoAdmCreateDto::class), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function create(#[SerializeParam] VideoAdmCreateDto $dto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_CREATE, $assetLicence);

        return $this->createdResponse(
            VideoFileAdmDetailDto::getInstance($this->videoFacade->createAssetFile($dto, $assetLicence))
        );
    }

    /**
     * Create an VideoFile with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('video'), OARequest(VideoAdmUpdateDto::class), OAResponse(VideoAdmUpdateDto::class), OAResponseValidation]
    public function update(VideoFile $video, #[SerializeParam] VideoAdmUpdateDto $newVideo): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);
        $newVideo->setVideoFile($video);

        return $this->okResponse(
            VideoAdmUpdateDto::getInstance($this->videoFacade->update($video, $newVideo))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route('/{video}/distribution-preview/{distribution}', name: 'set_distribution_preview', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('video'), OAParameterPath('distribution'), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function setDistributionPreview(VideoFile $video, Distribution $distribution): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distribution);

        return $this->okResponse(
            ImageFileAdmDetailDto::getInstance(
                $this->videoDistributionFacade->setDistributionPreview($video, $distribution)
            )
        );
    }

    /**
     * @throws ORMException
     */
    #[Route('/{video}/distribution-preview', name: 'get_distribution_preview_list', methods: [Request::METHOD_GET])]
    #[OAParameterPath('video'), OAResponse([DistributionImagePreviewAdmDto::class])]
    public function getDistributionImagePreviewList(VideoFile $video, ApiParams $apiParams): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_VIEW, $video);

        return $this->okResponse(
            $this->videoDistributionFacade->getPreview($apiParams, $video)
        );
    }

    /**
     * Create video for asset and assign to specific position.
     *
     * @throws ValidationException
     * @throws ForbiddenOperationException
     * @throws InvalidExtSystemConfigurationException
     * @throws AssetSlotUsedException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/asset/{asset}/slot-name/{slotName}', name: 'create_to_asset', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(VideoAdmCreateDto::class), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function createToAsset(Asset $asset, #[SerializeParam] VideoAdmCreateDto $video, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $asset);

        return $this->createdResponse(
            VideoFileAdmDetailDto::getInstance($this->videoFacade->addAssetFileToAsset($asset, $video, $slotName))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}/asset/{asset}/slot-name/{slotName}', name: 'set_to_slot', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('video'), OAParameterPath('asset'), OAParameterPath('slotName'), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function setToSlot(Asset $asset, VideoFile $video, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);

        return $this->okResponse(
            VideoFileAdmDetailDto::getInstance($this->videoPositionFacade->setToSlot($asset, $video, $slotName))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}/asset/{asset}/slot-name/{slotName}', name: 'remote_from_slot', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('video'), OAParameterPath('asset'), OAParameterPath('slotName')]
    public function removeFromSlot(Asset $asset, VideoFile $video, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);

        $this->videoPositionFacade->removeFromSlot($asset, $video, $slotName);

        return $this->noContentResponse();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}/asset/{asset}/main', name: 'set_main', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('video'), OAParameterPath('asset'), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function setMain(Asset $asset, VideoFile $video): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);

        return $this->okResponse(
            VideoFileAdmDetailDto::getInstance($this->videoPositionFacade->setMainFile($asset, $video))
        );
    }

    /**
     * Add chunk to VideoFile
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}/chunk', name: 'add_chunk', methods: [Request::METHOD_POST])]
    #[OAParameterPath('video'), OARequest(ChunkAdmCreateDto::class), OAResponse(Chunk::class), OAResponseValidation]
    public function addChunk(VideoFile $video, ChunkAdmCreateDto $chunk): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);

        return $this->createdResponse(
            $this->chunkFacade->create($chunk, $video)
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/{video}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(VideoFileAdmDetailDto::class)]
    public function getOne(VideoFile $video): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_VIEW, $video);

        return $this->okResponse(VideoFileAdmDetailDto::getInstance($video));
    }

    /**
     * Finish upload and start postprocess
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}/uploaded', name: 'finish_upload', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('video'), OARequest(AssetAdmFinishDto::class), OAResponse(VideoFileAdmDetailDto::class), OAResponseValidation]
    public function finishUpload(#[SerializeParam] AssetAdmFinishDto $assetFinishDto, VideoFile $video): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_UPDATE, $video);

        return $this->okResponse(
            VideoFileAdmDetailDto::getInstance(
                $this->statusFacade->finishUpload($assetFinishDto, $video)
            )
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{video}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('video'), OAResponseValidation]
    public function delete(VideoFile $video): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_DELETE, $video);

        $this->videoFacade->delete($video);

        return $this->noContentResponse();
    }

    #[Route(path: '/{video}/download-link', name: 'download_link', methods: [Request::METHOD_GET])]
    #[OAParameterPath('video'), OAResponseValidation]
    public function generateDownloadUrl(VideoFile $video): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_VIDEO_VIEW, $video);

        return $this->okResponse(
            $this->assetFileDownloadFacade->decorateDownloadLink($video)
        );
    }
}
