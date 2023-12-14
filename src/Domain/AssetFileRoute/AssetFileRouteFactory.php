<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFileRouteAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AssetFileRouteFactory extends AbstractManager
{
    use FileHelperTrait;

    private const PATH_TEMPLATE = '%s/%s.%s';

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly AssetFileRouteManager $routeManager,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
    ) {
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function createFromDto(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto): AssetFileRoute
    {
        $mainRoute = $this->assetFileRouteRepository->findMainByAssetFile((string) $assetFile->getId());
        if ($mainRoute) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        $slug = $this->createSlug($assetFile, $dto);
        $path = $this->createPath($assetFile, $slug);

        $existingRoute = $this->assetFileRouteRepository->findOneByUriPath($path);
        if ($existingRoute) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        $route = (new AssetFileRoute())
            ->setUri(
                (new RouteUri())
                    ->setSlug($slug)
                    ->setMain(true)
                    ->setPath($path)
            )
            ->setStatus(RouteStatus::Active)
            ->setMode($this->getMode($assetFile))
        ;
        $route->setTargetAssetFile($assetFile);
        $assetFile->getRoutes()->add($route);
        $assetFile->setMainRoute($route);

        return $this->routeManager->create($route, false);
    }

    private function createPath(AssetFile $assetFile, string $slug): string
    {
        return sprintf(
            self::PATH_TEMPLATE,
            $assetFile->getId(),
            $slug,
            $this->fileHelper->guessExtension($assetFile->getAssetAttributes()->getMimeType())
        );
    }

    private function createSlug(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto): string
    {
        return empty($dto->getSlug())
            ? $this->slugger->slug(
                empty($assetFile->getAsset()->getTexts()->getDisplayTitle())
                    ? (string) $assetFile->getId()
                    : $assetFile->getAsset()->getTexts()->getDisplayTitle(),
            )->toString()
            : $dto->getSlug()
        ;
    }

    private function getMode(AssetFile $assetFile): RouteMode
    {
        if ($assetFile instanceof AudioFile) {
            return RouteMode::StorageCopy;
        }

        return RouteMode::Direct;
    }
}
