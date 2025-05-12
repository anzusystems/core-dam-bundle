<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\Attributes\ArrayStringParam;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSiblingFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataBulkFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\Decorator\AssetAdmElasticsearchDecorator;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchLicenceCollectionDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Attributes\SerializeIterableParam;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmListDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\AssetAdmRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/asset', name: 'adm_asset_v1_')]
#[OA\Tag('Asset')]
final class AssetController extends AbstractApiController
{
    private const int IDS_LIMIT = 50;

    public function __construct(
        private readonly AssetFacade $assetFacade,
        private readonly AssetAdmElasticsearchDecorator $elasticSearch,
        private readonly AssetMetadataBulkFacade $assetMetadataBulkFacade,
        private readonly AssetAdmRepositoryDecorator $admRepositoryDecorator,
        private readonly AssetSiblingFacade $assetSiblingFacade,
    ) {
    }

    /**
     * Create empty asset
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/licence/{assetLicence}', name: 'create', methods: [Request::METHOD_POST])]
    #[OARequest(AssetAdmCreateDto::class), OAResponseCreated(AssetAdmDetailDto::class), OAResponseValidation]
    public function create(Request $request, #[SerializeParam] AssetAdmCreateDto $assetDto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $assetLicence);
        $asset = $this->assetFacade->create($assetDto, $assetLicence);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        return $this->createdResponse(
            AssetAdmDetailDto::getInstance($asset)
        );
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     * @throws ElasticsearchException
     */
    #[Route('/licence/{assetLicence}/search', name: 'search_by_licence', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmListDto::class])]
    public function searchByLicence(AssetLicence $assetLicence, #[SerializeParam] AssetAdmSearchLicenceCollectionDto $searchDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetLicence);
        $searchDto->setLicences(new ArrayCollection([$assetLicence]));

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteList($searchDto)
        );
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     * @throws ElasticsearchException
     */
    #[Route('/licence/search', name: 'search', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmListDto::class])]
    public function search(#[SerializeParam] AssetAdmSearchLicenceCollectionDto $searchDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $searchDto);

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteList($searchDto)
        );
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws ElasticsearchException
     */
    #[Route('/ext-system/{extSystem}/search', name: 'search_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmListDto::class])]
    public function searchByExtSystem(ExtSystem $extSystem, #[SerializeParam] AssetAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $extSystem);

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteListByExtSystem($searchDto, $extSystem)
        );
    }

    #[Route('/licence/{assetLicence}/ids/{ids}', name: 'get_by_licence_and_ids', methods: [Request::METHOD_GET])]
    public function getByLicenceAndIds(
        AssetLicence $assetLicence,
        #[ArrayStringParam(itemsLimit: self::IDS_LIMIT)]
        array $ids,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetLicence);

        return $this->okResponse(
            $this->admRepositoryDecorator->findByLicenceAndIds($assetLicence, $ids)
        );
    }

    #[Route('/ext-system/{extSystem}/ids/{ids}', name: 'get_by_ext_system_and_ids', methods: [Request::METHOD_GET])]
    public function getByExtSystemAndIds(
        ExtSystem $extSystem,
        #[ArrayStringParam(itemsLimit: self::IDS_LIMIT)]
        array $ids,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $extSystem);

        return $this->okResponse(
            $this->admRepositoryDecorator->findByExtSystemAndIds($extSystem, $ids)
        );
    }

    /**
     * Get one item.
     */
    #[Route(path: '/{asset}', name: 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetAdmDetailDto::class)]
    public function getOne(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $asset);

        return $this->okResponse(AssetAdmDetailDto::getInstance($asset));
    }

    /**
     * Get one item.
     */
    #[Route(path: '/asset-file/{assetFile}', name: 'get_one_by_file', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetAdmDetailDto::class)]
    public function getOneByAssetFile(AssetFile $assetFile): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetFile->getAsset());

        return $this->okResponse(AssetAdmDetailDto::getInstance($assetFile->getAsset()));
    }

    /**
     * Patch bulk of assets
     *
     * @throws ValidationException
     * @throws NonUniqueResultException
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/metadata-bulk-update', name: 'metadata_bulk_update', methods: [Request::METHOD_PATCH])]
    #[OARequest([FormProvidableMetadataBulkUpdateDto::class]), OAResponse([FormProvidableMetadataBulkUpdateDto::class]), OAResponseValidation]
    public function metadataBulkUpdate(Request $request, #[SerializeIterableParam(type: FormProvidableMetadataBulkUpdateDto::class)] Collection $list): JsonResponse
    {
        App::throwOnReadOnlyMode();
        AuditLogResourceHelper::setResource(
            request: $request,
            resourceName: Asset::getResourceName(),
            resourceId: CollectionHelper::traversableToIds($list, static fn (FormProvidableMetadataBulkUpdateDto $dto): string => (string) $dto->getAsset()->getId()),
        );

        return $this->okResponse(
            $this->assetMetadataBulkFacade->bulkUpdate($list)
        );
    }

    /**
     * Update item.
     *
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{asset}', name: 'update', methods: [Request::METHOD_PUT])]
    #[OAParameterPath('author'), OARequest(AssetAdmUpdateDto::class), OAResponse(AssetAdmUpdateDto::class), OAResponseValidation]
    public function update(Request $request, Asset $asset, #[SerializeParam] AssetAdmUpdateDto $newAssetDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        return $this->okResponse(
            AssetAdmUpdateDto::getInstance($this->assetFacade->update($asset, $newAssetDto))
        );
    }

    /**
     * @throws ForbiddenOperationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/{asset}/sibling/{targetAsset}', name: 'update_sibling', methods: [Request::METHOD_PATCH])]
    #[Route('/{asset}/sibling', name: 'remove_sibling', methods: [Request::METHOD_PATCH])]
    #[OAParameterPath('asset'), OAParameterPath('targetAsset'), OAResponseValidation]
    public function updateSibling(Request $request, Asset $asset, ?Asset $targetAsset = null): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        return $this->okResponse(
            AssetAdmDetailDto::getInstance($this->assetSiblingFacade->updateSibling($asset, $targetAsset))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{asset}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('asset'), OAResponseValidation]
    public function delete(Request $request, Asset $asset): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_DELETE, $asset);

        $this->assetFacade->toDeleting($asset);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $asset);

        return $this->noContentResponse();
    }
}
