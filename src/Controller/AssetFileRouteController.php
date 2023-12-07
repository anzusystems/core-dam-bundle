<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Cache\AssetFileCacheManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\AllowListConfiguration;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Image\Crop\CropFacade;
use AnzuSystems\CoreDamBundle\Domain\Image\ImageUrlFactory;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Exception\ImageManipulatorException;
use AnzuSystems\CoreDamBundle\Exception\InvalidCropException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Repository\ImageFileRepository;
use AnzuSystems\CoreDamBundle\Repository\RegionOfInterestRepository;
use Doctrine\ORM\NonUniqueResultException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '', name: 'asset_file_route_')]
final class AssetFileRouteController extends AbstractImageController
{
    public function __construct(
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    #[Route(
        path: '/{uri}',
        name: 'get_one',
        requirements: [
            'uri' => '.+',
        ],
        methods: [Request::METHOD_GET],
        priority: -1
    )]
    public function getOne(
        string $uri,
    ): Response {
        $route = $this->assetFileRouteRepository->findOneByUriPath($uri);
        if (
            null === $route ||
            false === $this->isDomainValid($route) ||
            $route->getStatus()->is(RouteStatus::Disabled)
        ) {
            return $this->notFoundResponse();
        }

        if ($route->getUri()->isMain() && $route->getMode()->is(RouteMode::StorageCopy)) {
            return $this->notFoundResponse();
        }

        if ($route->getUri()->isMain()) {
            return $this->streamResponse($route->getTargetAssetFile());
        }

        $activeRoute = $this->assetFileRouteRepository->findMainByAssetFile(
            assetId: (string) $route->getTargetAssetFile()->getId()
        );

        if (null === $activeRoute) {
            return $this->notFoundResponse();
        }

        return $this->redirectToAssetFileRoute($activeRoute);
    }

    private function redirectToAssetFileRoute(AssetFileRoute $route): RedirectResponse
    {
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAssetFile(
            asset: $route->getTargetAssetFile()
        );

        if (false === ($config instanceof AssetFileRouteConfigurableInterface)) {
            throw new NotFoundHttpException();
        }

        $path = $this->generateUrl(
            route: 'asset_file_route_get_one',
            parameters: [
                'uri' => $route->getUri()->getPath(),
            ]
        );

        return $this->redirect(
            UrlHelper::concatPathWithDomain(
                $config->getPublicDomainName(),
                $path
            )
        );
    }

    private function isDomainValid(AssetFileRoute $route): bool
    {
        $routeUriSchemeAndHost = $route->getUri()->getSchemeAndHost();
        if ($routeUriSchemeAndHost && $this->domainProvider->domainAndHostEquals($routeUriSchemeAndHost)) {
            return true;
        }

        return null === $routeUriSchemeAndHost &&
            $this->domainProvider->isCurrentSchemeAndHostPublicDomain($route->getTargetAssetFile())
        ;
    }
}
