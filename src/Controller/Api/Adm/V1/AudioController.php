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
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioFacade;
use AnzuSystems\CoreDamBundle\Domain\Audio\AudioStatusFacade;
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Exception\AssetSlotUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidExtSystemConfigurationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\ChunkParamConverter;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/audio', name: 'adm_audio_v1_')]
#[OA\Tag('Audio')]
final class AudioController extends AbstractApiController
{
    public function __construct(
        private readonly AudioFacade $audioFacade,
        private readonly AudioStatusFacade $statusFacade,
        private readonly ChunkFacade $chunkFacade,
        private readonly AssetFileDownloadFacade $assetFileDownloadFacade,
    ) {
    }

    /**
     * Upload an audio with specific licence from external provider
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}/external-provider', name: 'upload_from_external_provider', methods: [Request::METHOD_POST])]
    #[ParamConverter('uploadDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(UploadAssetFromExternalProviderDto::class), OAResponse(AudioFileAdmDetailDto::class), OAResponseValidation]
    public function uploadFromExternalProvider(UploadAssetFromExternalProviderDto $uploadDto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $uploadDto->getExternalProvider());
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_CREATE, $assetLicence);

        return $this->createdResponse(
            AudioFileAdmDetailDto::getInstance(
                $this->audioFacade->createAssetFilesFromExternalProvider($uploadDto, $assetLicence)
            )
        );
    }

    /**
     * Create an AudioFile with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}', name: 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('dto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(AudioAdmCreateDto::class), OAResponse(AudioFileAdmDetailDto::class), OAResponseValidation]
    public function create(AudioAdmCreateDto $dto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_CREATE, $assetLicence);

        return $this->createdResponse(
            AudioFileAdmDetailDto::getInstance($this->audioFacade->createAssetFile($dto, $assetLicence))
        );
    }

    /**
     * Create audio for asset and assign to specific position.
     *
     * @throws ValidationException
     * @throws ForbiddenOperationException
     * @throws InvalidExtSystemConfigurationException
     * @throws AssetSlotUsedException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/asset/{asset}/slot-name/{slotName}', name: 'create_to_asset', methods: [Request::METHOD_POST])]
    #[ParamConverter('audio', converter: SerializerParamConverter::class)]
    #[OAParameterPath('assetLicence'), OARequest(ImageAdmCreateDto::class), OAResponse(ImageFileAdmDetailDto::class), OAResponseValidation]
    public function createToAsset(Asset $asset, AudioAdmCreateDto $audio, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_CREATE, $asset);

        return $this->createdResponse(
            AudioFileAdmDetailDto::getInstance($this->audioFacade->addAssetFileToAsset($asset, $audio, $slotName))
        );
    }

    /**
     * Add chunk to AudioFile
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{audio}/chunk', name: 'add_chunk', methods: [Request::METHOD_POST])]
    #[ParamConverter('chunk', converter: ChunkParamConverter::class)]
    #[OAParameterPath('audio'), OARequest(ChunkAdmCreateDto::class), OAResponse(Chunk::class), OAResponseValidation]
    public function addChunk(AudioFile $audio, ChunkAdmCreateDto $chunk): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_CREATE, $audio);

        return $this->createdResponse(
            $this->chunkFacade->create($chunk, $audio)
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/{audio}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(AudioFileAdmDetailDto::class)]
    public function getOne(AudioFile $audio): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_VIEW, $audio);

        return $this->okResponse(AudioFileAdmDetailDto::getInstance($audio));
    }

    /**
     * Finish upload and start postprocess
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{audio}/uploaded', name: 'finish_upload', methods: [Request::METHOD_PATCH])]
    #[ParamConverter('assetFinishDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('audio'), OARequest(AssetAdmFinishDto::class), OAResponse(AudioFileAdmDetailDto::class), OAResponseValidation]
    public function finishUpload(AssetAdmFinishDto $assetFinishDto, AudioFile $audio): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_CREATE, $audio);

        return $this->okResponse(
            AudioFileAdmDetailDto::getInstance(
                $this->statusFacade->finishUpload($assetFinishDto, $audio)
            )
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{audio}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('audio'), OAResponseValidation]
    public function delete(AudioFile $audio): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_DELETE, $audio);

        $this->audioFacade->delete($audio);

        return $this->noContentResponse();
    }


    #[Route(path: '/{audio}/download-link', name: 'download_link', methods: [Request::METHOD_GET])]
    #[OAParameterPath('audio'), OAResponseValidation]
    public function generateDownloadUrl(AudioFile $audio): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_AUDIO_VIEW, $audio);

        return $this->okResponse(
            $this->assetFileDownloadFacade->decorateDownloadLink($audio)
        );
    }
}
