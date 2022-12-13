<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
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
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\FormProvidableMetadataBulkUpdateDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Repository\Decorator\AssetAdmRepositoryDecorator;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\ArrayStringParamConverter;
use AnzuSystems\CoreDamBundle\Request\ParamConverter\CollectionParamConverter;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
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
    #[ParamConverter('assetDto', converter: SerializerParamConverter::class)]
    #[OARequest(AssetAdmCreateDto::class), OAResponseCreated(AssetAdmDetailDto::class), OAResponseValidation]
    public function create(AssetAdmCreateDto $assetDto, AssetLicence $assetLicence): JsonResponse
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
    #[ParamConverter('searchDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmDetailDto::class])]
    public function searchByLicence(AssetLicence $assetLicence, AssetAdmSearchDto $searchDto): JsonResponse
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
    #[ParamConverter('searchDto', converter: SerializerParamConverter::class)]
    #[OAParameterPath('search', description: 'Searched asset.'), OAResponse([AssetAdmDetailDto::class])]
    public function searchByExtSystem(ExtSystem $extSystem, AssetAdmSearchDto $searchDto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $extSystem);

        return $this->okResponse(
            $this->elasticSearch->searchInfiniteList($searchDto, $extSystem)
        );
    }

    #[Route('/licence/{assetLicence}/ids/{ids}', name: 'get_by_licence_and_ids', methods: [Request::METHOD_GET])]
    #[ParamConverter('ids', options: [ArrayStringParamConverter::ITEMS_LIMIT => self::IDS_LIMIT], converter: ArrayStringParamConverter::class)]
    public function getByLicenceAndIds(AssetLicence $assetLicence, array $ids): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetLicence);

        return $this->okResponse(
            $this->admRepositoryDecorator->findByLicenceAndIds($assetLicence, $ids)
        );
    }

    #[Route('/ext-system/{extSystem}/ids/{ids}', name: 'get_by_ext_system_and_ids', methods: [Request::METHOD_GET])]
    #[ParamConverter('ids', options: [ArrayStringParamConverter::ITEMS_LIMIT => self::IDS_LIMIT], converter: ArrayStringParamConverter::class)]
    public function getByExtSystemAndIds(ExtSystem $extSystem, array $ids): JsonResponse
    {
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
    #[ParamConverter('list', class: FormProvidableMetadataBulkUpdateDto::class, converter: CollectionParamConverter::class)]
    #[OARequest([FormProvidableMetadataBulkUpdateDto::class]), OAResponse([FormProvidableMetadataBulkUpdateDto::class]), OAResponseValidation]
    public function metadataBulkUpdate(Collection $list): JsonResponse
    {
        App::throwOnReadOnlyMode();

        return $this->okResponse(
            $this->assetMetadataBulkFacade->bulkUpdate($list)
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
