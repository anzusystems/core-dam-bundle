<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetSlot;
use AnzuSystems\CoreDamBundle\Model\Dto\Asset\AssetAdmDetailDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileMetadata\AssetSlotAdmListDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/asset-file', name: 'adm_asset_file_v1_')]
#[OA\Tag('AssetFile')]
final class AssetFileController extends AbstractApiController
{
    #[Route(path: '/asset/{asset}', name: 'list_by_asset', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponse(AssetAdmDetailDto::class), OAResponseValidation]
    public function create(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $asset);
        // todo permissions based on type

        return $this->okResponse(
            (new ApiResponseList())
                ->setData(
                    $asset->getSlots()->map(
                        fn (AssetSlot $assetSlot): AssetSlotAdmListDto => AssetSlotAdmListDto::getInstance($assetSlot)
                    )->toArray()
                )
                ->setTotalCount(
                    $asset->getSlots()->count()
                )
        );
    }
}
