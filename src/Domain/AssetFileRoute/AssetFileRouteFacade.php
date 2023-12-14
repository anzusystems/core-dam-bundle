<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Event\AssetFileRouteEvent;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFileRouteAdmCreateDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Traits\EventDispatcherAwareTrait;
use AnzuSystems\CoreDamBundle\Traits\FileHelperTrait;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

final class AssetFileRouteFacade extends AbstractManager
{
    use FileHelperTrait;
    use EventDispatcherAwareTrait;
    private const PATH_TEMPLATE = '%s/%s.%s';

    public function __construct(
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly AssetFileRouteFactory $routeFactory,
        private readonly AssetFileRouteManager $assetFileRouteManager,
        private readonly AssetFileRouteStorageManager $assetFileRouteStorageManager,
        private readonly SluggerInterface $slugger,
        private readonly AssetFileRouteManager $routeManager,
        private readonly FileSystemProvider $fileSystemProvider,
        private readonly AssetFileRouteGenerator $assetFileRouteGenerator,
    ) {
    }

    public function makePublic(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto): AssetFileRoute
    {
        $this->validateProcessState($assetFile);
        $route = $this->routeFactory->createFromDto($assetFile, $dto);

        try {
            $this->assetFileRouteManager->beginTransaction();

            if ($assetFile instanceof AudioFile) {
                $this->makeAudioPublicLegacy($assetFile, $route);
            }
            if ($route->getMode()->is(RouteMode::StorageCopy)) {
                $this->assetFileRouteStorageManager->writeRouteFile($assetFile, $route);
            }
            $this->assetFileRouteManager->flush();
            $this->assetFileRouteManager->commit();

            $this->dispatcher->dispatch($this->createEvent($route));
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw new RuntimeException('asset_route_create_failed', 0, $e);
        }

        return $route;
    }

    public function makePrivate(AssetFile $assetFile): AssetFile
    {
        $mainRoute = $this->assetFileRouteRepository->findMainByAssetFile((string) $assetFile->getId());
        if (null === $mainRoute) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        try {
            $this->assetFileRouteManager->beginTransaction();
            $path = $mainRoute->getUri()->getPath();
            $event = $this->createEvent($mainRoute);
            $this->assetFileRouteManager->delete($mainRoute);

            if ($assetFile instanceof AudioFile) {
                $this->makeAudioPrivateLegacy($assetFile);
            }
            if ($mainRoute->getMode()->is(RouteMode::StorageCopy)) {
                $this->assetFileRouteStorageManager->deleteRouteFile($assetFile, $path);
            }

            $this->assetFileRouteManager->flush();
            $this->assetFileRouteManager->commit();

            $this->dispatcher->dispatch($event);
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
            ->setSlug($route->getUri()->getSlug())
            ->setPath($route->getUri()->getPath())
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

    private function createEvent(AssetFileRoute $route): AssetFileRouteEvent
    {
        return new AssetFileRouteEvent(
            (string) $route->getTargetAssetFile()->getId(),
            $this->assetFileRouteGenerator->getFullUrl($route)
        );
    }
}
