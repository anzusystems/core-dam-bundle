<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
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

    private const string PATH_TEMPLATE = '%s/%s.%s';

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

    public function makePublicAssetFile(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto = null): AssetFileRoute
    {
        return $assetFile instanceof ImageFile
            ? $this->makeImagePublic($assetFile)
            : $this->makePublicFromDto($assetFile, $dto ?? new AssetFileRouteAdmCreateDto());
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function makeImagePublic(ImageFile $imageFile): AssetFileRoute
    {
        $this->validateProcessState($imageFile);
        $this->validateMainRouteExists($imageFile);

        return $this->makePublic(
            assetFile: $imageFile,
            route: $this->routeFactory->createForImage($imageFile)
        );
    }

    /**
     * @throws ForbiddenOperationException
     */
    public function makePublicFromDto(AssetFile $assetFile, AssetFileRouteAdmCreateDto $dto): AssetFileRoute
    {
        $this->validateProcessState($assetFile);
        $this->validateMainRouteExists($assetFile);

        return $this->makePublic(
            assetFile: $assetFile,
            route: $this->routeFactory->createFromDto($assetFile, $dto)
        );
    }

    /**
     * @throws ForbiddenOperationException
     */
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

    private function makePublic(AssetFile $assetFile, AssetFileRoute $route): AssetFileRoute
    {
        try {
            $this->assetFileRouteManager->beginTransaction();

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

    /**
     * @throws ForbiddenOperationException
     */
    private function validateProcessState(AssetFile $assetFile): void
    {
        if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }

    /**
     * @throws ForbiddenOperationException
     */
    private function validateMainRouteExists(AssetFile $assetFile): void
    {
        $mainRoute = $this->assetFileRouteRepository->findMainByAssetFile((string) $assetFile->getId());
        if (null === $mainRoute) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }

    private function createEvent(AssetFileRoute $route): AssetFileRouteEvent
    {
        return new AssetFileRouteEvent(
            (string) $route->getTargetAssetFile()->getId(),
            $this->assetFileRouteGenerator->getFullUrl($route)
        );
    }
}
