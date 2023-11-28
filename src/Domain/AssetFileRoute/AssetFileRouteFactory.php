<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Asset;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFilePublicRouteAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use Google\Service\Compute\Route;
use League\Flysystem\FilesystemException;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AssetFileRouteFactory extends AbstractManager
{
    use FileHelperTrait;

    private const PATH_TEMPLATE = '%s/%s.%s';

    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly AssetFileRouteManager $routeManager,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    public function createFromDto(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): AssetFileRoute
    {
        $slug = $this->createSlug($assetFile, $dto);

        $route = (new AssetFileRoute())
            ->setSlug($slug)
            ->setPath($this->createPath($assetFile, $slug))
        ;
        $assetFile->setRoute($route);
        $route->setAssetFile($assetFile);

        return $this->routeManager->create($route, false);
    }

    private function createSlug(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): string
    {
        return empty($dto->getSlug())
            ? $this->slugger->slug(
                empty($assetFile->getAsset()->getTexts()->getDisplayTitle())
                    ? $assetFile->getId()
                    : $assetFile->getAsset()->getTexts()->getDisplayTitle(),
            )->toString()
            : $dto->getSlug()
        ;
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
}
