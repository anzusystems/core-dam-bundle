<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
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

    private const string PATH_TEMPLATE = '%s/%s.%s';
    private const string IMAGE_PATH_TEMPLATE = 'image/original/%s.jpg';

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly AssetFileRouteManager $routeManager,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
    ) {
    }

    public function createForImage(ImageFile $imageFile): AssetFileRoute
    {
        return $this->createFileRoute(
            assetFile: $imageFile,
            slug: '',
            path: $this->createPathForImage($imageFile)
        );
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function createFromDto(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto): AssetFileRoute
    {
        $slug = $this->createSlug($assetFile, $dto);

        return $this->createFileRoute(
            assetFile: $assetFile,
            slug: $slug,
            path: $this->createPath($assetFile, $slug)
        );
    }

    private function createFileRoute(AssetFile $assetFile, string $slug, string $path): AssetFileRoute
    {
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

    private function createPathForImage(ImageFile $imageFile): string
    {
        return sprintf(
            self::IMAGE_PATH_TEMPLATE,
            $imageFile->getId(),
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
