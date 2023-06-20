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
use AnzuSystems\CoreDamBundle\Domain\Chunk\ChunkFacade;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentFacade;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentPositionFacade;
use AnzuSystems\CoreDamBundle\Domain\Document\DocumentStatusFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\Chunk;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\AssetSlotUsedException;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\InvalidExtSystemConfigurationException;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmFinishDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetExternalProvider\UploadAssetFromExternalProviderDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Chunk\ChunkAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmDetailDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/document', name: 'adm_document_v1_')]
#[OA\Tag('Document')]
final class DocumentController extends AbstractApiController
{
    public function __construct(
        private readonly DocumentFacade $documentFacade,
        private readonly DocumentStatusFacade $statusFacade,
        private readonly ChunkFacade $chunkFacade,
        private readonly AssetFileDownloadFacade $assetFileDownloadFacade,
        private readonly DocumentPositionFacade $documentPositionFacade,
    ) {
    }

    /**
     * Upload a document with specific licence from external provider
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}/external-provider', name: 'upload_from_external_provider', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(UploadAssetFromExternalProviderDto::class), OAResponse(AudioFileAdmDetailDto::class), OAResponseValidation]
    public function uploadFromExternalProvider(#[SerializeParam] UploadAssetFromExternalProviderDto $uploadDto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_EXTERNAL_PROVIDER_ACCESS, $uploadDto->getExternalProvider());
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_CREATE, $assetLicence);

        return $this->createdResponse(
            DocumentFileAdmDetailDto::getInstance(
                $this->documentFacade->createAssetFilesFromExternalProvider($uploadDto, $assetLicence)
            )
        );
    }

    /**
     * Create an DocumentFile with specific licence
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}', name: 'create', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(DocumentAdmCreateDto::class), OAResponse(DocumentFileAdmDetailDto::class), OAResponseValidation]
    public function create(#[SerializeParam] DocumentAdmCreateDto $dto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_CREATE, $assetLicence);

        return $this->createdResponse(
            DocumentFileAdmDetailDto::getInstance($this->documentFacade->createAssetFile($dto, $assetLicence))
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
     * @throws NonUniqueResultException
     */
    #[Route(path: '/asset/{asset}/slot-name/{slotName}', name: 'create_to_asset', methods: [Request::METHOD_POST])]
    #[OAParameterPath('assetLicence'), OARequest(DocumentAdmCreateDto::class), OAResponse(DocumentFileAdmDetailDto::class), OAResponseValidation]
    public function createToAsset(Asset $asset, #[SerializeParam] DocumentAdmCreateDto $document, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $asset);

        return $this->createdResponse(
            DocumentFileAdmDetailDto::getInstance($this->documentFacade->addAssetFileToAsset($asset, $document, $slotName))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{document}/asset/{asset}/slot-name/{slotName}', name: 'set_to_slot', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('document'), OAParameterPath('asset'), OAParameterPath('slotName'), OAResponse(DocumentFileAdmDetailDto::class), OAResponseValidation]
    public function setToSlot(Asset $asset, DocumentFile $document, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $document);

        return $this->okResponse(
            DocumentFileAdmDetailDto::getInstance($this->documentPositionFacade->setToSlot($asset, $document, $slotName))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{document}/asset/{asset}/slot-name/{slotName}', name: 'remote_from_slot', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('document'), OAParameterPath('asset'), OAParameterPath('slotName')]
    public function removeFromSlot(Asset $asset, DocumentFile $document, string $slotName): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $document);

        $this->documentPositionFacade->removeFromSlot($asset, $document, $slotName);

        return $this->noContentResponse();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{document}/asset/{asset}/main', name: 'set_main', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('document'), OAParameterPath('asset'), OAResponse(DocumentFileAdmDetailDto::class), OAResponseValidation]
    public function setMain(Asset $asset, DocumentFile $document): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $document);

        return $this->okResponse(
            DocumentFileAdmDetailDto::getInstance($this->documentPositionFacade->setMainFile($asset, $document))
        );
    }

    /**
     * Add chunk to DocumentFile
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{document}/chunk', name: 'add_chunk', methods: [Request::METHOD_POST])]
    #[OAParameterPath('document'), OARequest(ChunkAdmCreateDto::class), OAResponse(Chunk::class), OAResponseValidation]
    public function addChunk(DocumentFile $document, ChunkAdmCreateDto $chunk): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $document);

        return $this->createdResponse(
            $this->chunkFacade->create($chunk, $document)
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/{document}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(DocumentFileAdmDetailDto::class)]
    public function getOne(DocumentFile $document): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_VIEW, $document);

        return $this->okResponse(DocumentFileAdmDetailDto::getInstance($document));
    }

    /**
     * Finish upload and start postprocess
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     * @throws SerializerException
     */
    #[Route(path: '/{document}/uploaded', name: 'finish_upload', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('document'), OARequest(AssetAdmFinishDto::class), OAResponse(DocumentFileAdmDetailDto::class), OAResponseValidation]
    public function finishUpload(#[SerializeParam] AssetAdmFinishDto $assetFinishDto, DocumentFile $document): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_UPDATE, $document);

        return $this->okResponse(
            DocumentFileAdmDetailDto::getInstance(
                $this->statusFacade->finishUpload($assetFinishDto, $document)
            )
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{document}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('document'), OAResponseValidation]
    public function delete(DocumentFile $document): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_DELETE, $document);

        $this->documentFacade->delete($document);

        return $this->noContentResponse();
    }

    #[Route(path: '/{document}/download-link', name: 'download_link', methods: [Request::METHOD_GET])]
    #[OAParameterPath('document'), OAResponseValidation]
    public function generateDownloadUrl(DocumentFile $document): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DOCUMENT_VIEW, $document);

        return $this->okResponse(
            $this->assetFileDownloadFacade->decorateDownloadLink($document)
        );
    }
}
