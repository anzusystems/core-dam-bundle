<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\AudioFile;
use AnzuSystems\CoreDamBundle\Entity\DocumentFile;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute\AssetFilePublicRouteAdmDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioPublicationAdmDto;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AssetFileRouteFacade extends AbstractManager
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly AssetFileRouteFactory $routeFactory,
        private readonly AssetFileRouteManager $assetFileRouteManager,
    ) {
    }

    public function makePublic(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): void
    {
        $this->validateProcessState($assetFile);
        $this->ensureSlug($assetFile, $dto);
        $route = $this->assetFileRouteRepository->findByAssetId((string) $assetFile->getId());

        if ($route) {
            throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
        }

        $route = $this->routeFactory->createFromDto($assetFile, $dto);
        dump($route);
    }

    private function validateProcessState(AssetFile $assetFile): void
    {
        if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Processed)) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }

    private function ensureSlug(AssetFile $assetFile, AssetFilePublicRouteAdmDto $dto): void
    {
        if (empty($dto->getSlug())) {
            $dto->setSlug(
                $this->slugger->slug($assetFile->getAsset()->getTexts()->getDisplayTitle())->toString()
            );
        }
    }

    private function validateTransition(AssetFile $assetFile, bool $publicExpected): void
    {
        if ($assetFile->getAudioPublicLink()->isPublic() === $publicExpected) {
            return;
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::ERROR_MESSAGE);
    }
}
