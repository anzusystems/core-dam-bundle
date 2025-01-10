<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Pub\V1;

use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationFacade;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '', name: 'pub_asset_v1_')]
#[OA\Tag('Asset')]
final class AssetController extends AbstractApiController
{
    public function __construct(
        private readonly ConfigurationFacade $configurationFacade,
    ) {
    }

    /**
     * Return app configuration
     */
    #[Route(path: '/{area}/{subscriptionRole}/asset', name: 'get', methods: [Request::METHOD_GET])]
    public function get(): JsonResponse
    {
        return $this->getResponse([

        ]);
        //        return $this->okResponse(
        //            $this->configurationFacade->decoratePub(),
        //            new AdmConfigCacheSettings()
        //        );
    }
}
