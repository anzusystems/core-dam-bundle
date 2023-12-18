<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysDetailDecorator;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use League\Flysystem\FilesystemException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag('AssetFile')]
#[Route('/asset-file', 'sys_asset_file_')]
final class AssetFileController extends AbstractApiController
{
    public function __construct(
        private readonly AssetSysFacade $assetSysFacade
    ) {
    }

    /**
     * @throws FilesystemException
     */
    #[Route('', 'create', methods: [Request::METHOD_POST])]
    #[OAResponse(AssetFileSysDetailDecorator::class), OAResponseValidation, OAResponseCreated]
    public function create(#[SerializeParam] AssetFileSysCreateDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $dto);

        return $this->okResponse(
            AssetFileSysDetailDecorator::getInstance($this->assetSysFacade->createFromDto($dto))
        );
    }

    #[Route('/{assetFile}', 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetFileSysDetailDecorator::class)]
    public function getOne(AssetFile $assetFile): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetFile->getAsset());

        return $this->okResponse(
            AssetFileSysDetailDecorator::getInstance($assetFile)
        );
    }
}
