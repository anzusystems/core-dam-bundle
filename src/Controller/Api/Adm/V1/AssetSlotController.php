<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\AssetSlot\AssetSlotFacade;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Model\Attributes\SerializeIterableParam;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetSlot\AssetSlotAdmListDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetSlot\AssetSlotMinimalAdmDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/asset-slot', name: 'adm_asset_slot_v1_')]
#[OA\Tag('AssetSlot')]
final class AssetSlotController extends AbstractApiController
{
    public function __construct(
        private readonly AssetSlotFacade $assetSlotFacade,
    ) {
    }

    #[Route(path: '/asset/{asset}', name: 'list_by_asset', methods: [Request::METHOD_GET])]
    #[OAParameterPath('asset'), OAResponse(AssetSlotAdmListDecorator::class), OAResponseValidation]
    public function list(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $asset);

        return $this->okResponse($this->assetSlotFacade->decorateAssetSlots($asset));
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     */
    #[Route(path: '/asset/{asset}', name: 'update', methods: [Request::METHOD_PATCH])]
    #[OARequest([AssetSlotMinimalAdmDto::class]), OAResponse([AssetSlotAdmListDecorator::class]), OAResponseValidation]
    public function update(Asset $asset, #[SerializeIterableParam(type: AssetSlotMinimalAdmDto::class)] Collection $list): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_UPDATE, $asset);

        return $this->okResponse(
            $this->assetSlotFacade->update($asset, $list)
        );
    }
}
