<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Pub\V1;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CoreDamBundle\Cache\Settings\AdmConfigCacheSettings;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationFacade;
use AnzuSystems\CoreDamBundle\Model\Dto\Configuration\ConfigurationPubGetDto;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/configuration', name: 'pub_configuration_v1_')]
#[OA\Tag('Configuration')]
final class ConfigurationController extends AbstractApiController
{
    public function __construct(
        private readonly ConfigurationFacade $configurationFacade,
    ) {
    }

    /**
     * Return app configuration
     */
    #[Route(path: '', name: 'get', methods: [Request::METHOD_GET])]
    #[OAResponse(ConfigurationPubGetDto::class)]
    public function get(): JsonResponse
    {
        return $this->okResponse(
            $this->configurationFacade->decoratePub(),
            new AdmConfigCacheSettings()
        );
    }
}
