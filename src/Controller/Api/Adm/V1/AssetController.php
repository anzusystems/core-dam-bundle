<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\Attributes\ArrayStringParam;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetFacade;
use AnzuSystems\CoreDamBundle\Domain\AssetMetadata\AssetMetadataBulkFacade;
use AnzuSystems\CoreDamBundle\Elasticsearch\Decorator\AssetAdmElasticsearchDecorator;
use AnzuSystems\CoreDamBundle\Elasticsearch\SearchDto\AssetAdmSearchDto;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Attributes\SerializeIterableParam;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmUpdateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\AssetAdmRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
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
    private const IDS_LIMIT = 50;

    public function __construct(
        private readonly AssetFacade $assetFacade,
        private readonly AssetAdmElasticsearchDecorator $elasticSearch,
        private readonly AssetMetadataBulkFacade $assetMetadataBulkFacade,
        private readonly AssetAdmRepositoryDecorator $admRepositoryDecorator,
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
    public function create(#[SerializeParam] AssetAdmCreateDto $assetDto, AssetLicence $assetLicence): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $assetLicence);

        return $this->createdResponse(
            AssetAdmDetailDto::getInstance($this->assetFacade->create($assetDto, $assetLicence))
        );
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/licence/{assetLicence}/search', name: 'search_by_licence', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmDetailDto::class])]
    public function searchByLicence(AssetLicence $assetLicence, #[SerializeParam] AssetAdmSearchDto $searchDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetLicence);
        $searchDto->setLicences([$assetLicence]);

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteList($searchDto, $assetLicence->getExtSystem())
        );
    }

    /**
     * @throws SerializerException
     * @throws ValidationException
     */
    #[Route('/ext-system/{extSystem}/search', name: 'search_by_ext_system', methods: [Request::METHOD_GET])]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmDetailDto::class])]
    public function searchByExtSystem(ExtSystem $extSystem, #[SerializeParam] AssetAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $extSystem);

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteList($searchDto, $extSystem)
        );
    }

    #[Route('/licence/{assetLicence}/ids/{ids}', name: 'get_by_licence_and_ids', methods: [Request::METHOD_GET])]
    public function getByLicenceAndIds(
        AssetLicence $assetLicence,
        #[ArrayStringParam(itemsLimit: self::IDS_LIMIT, itemNormalizer: 'intval')] array $ids,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetLicence);

        return $this->okResponse(
            $this->admRepositoryDecorator->findByLicenceAndIds($assetLicence, $ids)
        );
    }

    #[Route('/ext-system/{extSystem}/ids/{ids}', name: 'get_by_ext_system_and_ids', methods: [Request::METHOD_GET])]
    public function getByExtSystemAndIds(
        ExtSystem $extSystem,
        #[ArrayStringParam(itemsLimit: self::IDS_LIMIT, itemNormalizer: 'intval')] array $ids,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $extSystem);

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
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $asset);

        return $this->okResponse(AssetAdmDetailDto::getInstance($asset));
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
    public function metadataBulkUpdate(#[SerializeIterableParam(type: FormProvidableMetadataBulkUpdateDto::class)] Collection $list): JsonResponse
    {
        App::throwOnReadOnlyMode();

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
    public function update(Asset $asset, #[SerializeParam] AssetAdmUpdateDto $newAssetDto): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);

        return $this->okResponse(
            AssetAdmUpdateDto::getInstance($this->assetFacade->update($asset, $newAssetDto))
        );
    }

    /**
     * @throws AppReadOnlyModeException
     */
    #[Route(path: '/{asset}', name: 'delete', methods: [Request::METHOD_DELETE])]
    #[OAParameterPath('asset'), OAResponseValidation]
    public function delete(Asset $asset): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_DELETE, $asset);

        $this->assetFacade->toDeleting($asset);

        return $this->noContentResponse();
    }
}
