<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFilePublicRouteAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

final class AssetFileRouteFacade extends AbstractManager
{
    public function __construct(
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly AssetFileRouteFactory $routeFactory,
        private readonly AssetFileRouteManager $assetFileRouteManager,
        private readonly AssetFileRouteStorageManager $assetFileRouteStorageManager,
    ) {
    }

    // todo cache purge

    public function makePublic(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): AssetFile
    {
        $this->validateProcessState($assetFile);

        if ($assetFile->getRoute()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        try {
            $this->assetFileRouteManager->beginTransaction();
            $route = $this->routeFactory->createFromDto($assetFile, $dto);
            if ($assetFile instanceof AudioFile) {
                $this->makeAudioPublicLegacy($assetFile, $route);
            }
            $this->assetFileRouteStorageManager->writeRouteFile($assetFile, $route);
            $this->assetFileRouteManager->flush();
            $this->assetFileRouteManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw new RuntimeException('asset_route_create_failed', 0, $e);
        }

        return $assetFile;
    }

    public function makePrivate(AssetFile $assetFile): AssetFile
    {
        if (null === $assetFile->getRoute()) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        try {
            $this->assetFileRouteManager->beginTransaction();
            $path = $assetFile->getRoute()->getPath();

            $this->assetFileRouteManager->delete($assetFile->getRoute());

            if ($assetFile instanceof AudioFile) {
                $this->makeAudioPrivateLegacy($assetFile);
            }

            $this->assetFileRouteStorageManager->deleteRouteFile($assetFile, $path);
            $this->assetFileRouteManager->flush();
            $this->assetFileRouteManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw new RuntimeException('asset_route_delete_failed', 0, $e);
        }

        return $assetFile;
    }

    private function validateProcessState(AssetFile $assetFile): void
    {
        if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }

    private function makeAudioPublicLegacy(AudioFile $audioFile, AssetFileRoute $route): void
    {
        $audioFile->getAudioPublicLink()
            ->setSlug($route->getSlug())
            ->setPath($route->getPath())
            ->setPublic(true)
        ;
    }

    private function makeAudioPrivateLegacy(AudioFile $audioFile): void
    {
        $audioFile->getAudioPublicLink()
            ->setSlug('')
            ->setPath('')
            ->setPublic(false)
        ;
    }
}
