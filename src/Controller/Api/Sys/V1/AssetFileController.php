<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Sys\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseCreated;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Asset\AssetSysFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Exception\AssetFileProcessFailed;
use AnzuSystems\CoreDamBundle\Exception\InvalidMimeTypeException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysDetailDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysPathCreateDto;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFile\AssetFileSysUrlCreateDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use Doctrine\ORM\NonUniqueResultException;
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
     * @throws ValidationException
     * @throws InvalidMimeTypeException
     * @throws NonUniqueResultException
     */
    #[Route('', 'create', methods: [Request::METHOD_POST])]
    #[OARequest(AssetFileSysPathCreateDto::class), OAResponse(AssetFileSysDetailDecorator::class), OAResponseValidation, OAResponseCreated]
    public function create(Request $request, #[SerializeParam] AssetFileSysPathCreateDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $dto);
        $assetFile = $this->assetSysFacade->createFromFileDto($dto);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetFile);

        return $this->okResponse(AssetFileSysDetailDecorator::getInstance($assetFile));
    }

    /**
     * @throws FilesystemException
     * @throws NonUniqueResultException
     * @throws ValidationException
     * @throws AssetFileProcessFailed
     */
    #[Route('/from-url', 'create_from_url', methods: [Request::METHOD_POST])]
    #[OARequest(AssetFileSysUrlCreateDto::class), OAResponse(AssetFileSysDetailDecorator::class), OAResponseValidation, OAResponseCreated]
    public function createFromUrl(Request $request, #[SerializeParam] AssetFileSysUrlCreateDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_CREATE, $dto);
        $assetFile = $this->assetSysFacade->createFromUrlDto($dto);
        AuditLogResourceHelper::setResourceByEntity(request: $request, entity: $assetFile);

        return $this->okResponse(AssetFileSysDetailDecorator::getInstance($assetFile));
    }

    #[Route('/{assetFile}', 'get_one', methods: [Request::METHOD_GET])]
    #[OAResponse(AssetFileSysDetailDecorator::class)]
    public function getOne(AssetFile $assetFile): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_READ, $assetFile->getAsset());

        return $this->okResponse(
            AssetFileSysDetailDecorator::getInstance($assetFile)
        );
    }
}
