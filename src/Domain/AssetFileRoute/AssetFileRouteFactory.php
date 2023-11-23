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
use Google\Service\Compute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AssetFileRouteFactory extends AbstractManager
{
    public function __construct(
        private readonly AssetFileRouteManager $routeManager,
        private readonly FileSystemProvider $fileSystemProvider,
    ) {
    }

    public function createFromDto(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): AssetFileRoute
    {
        $route = (new AssetFileRoute())
            ->setAssetFileId((string) $assetFile->getId())
            ->setSlug($dto->getSlug())
        ;

        $publicFilesystem = $this->fileSystemProvider->getPublicFilesystem($assetFile);
        dump($publicFilesystem);

        return $this->routeManager->create($route, false);
    }
}
