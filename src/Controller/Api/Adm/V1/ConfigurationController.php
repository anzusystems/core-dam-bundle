<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CoreDamBundle\Cache\Settings\AdmConfigCacheSettings;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationFacade;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Decorator\ExtSystemAdmGetDecorator;
use AnzuSystems\CoreDamBundle\Model\Dto\Configuration\ConfigurationAdmGetDto;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(path: '/configuration', name: 'adm_configuration_v1_')]
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
    #[OAResponse(ConfigurationAdmGetDto::class)]
    public function get(): JsonResponse
    {
        return $this->okResponse(
            $this->configurationFacade->decorateAdm(),
            new AdmConfigCacheSettings()
        );
    }

    /**
     * Return ext system configuration
     */
    #[Route(path: '/ext-system/{extSystem}', name: 'get_ext_system', methods: [Request::METHOD_GET])]
    #[OAResponse(ExtSystemAdmGetDecorator::class)]
    public function getExtSystem(ExtSystem $extSystem): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_EXT_SYSTEM_VIEW, $extSystem);

        return $this->okResponse(
            $this->configurationFacade->decorateExtSystemAdm($extSystem),
            new AdmConfigCacheSettings()
        );
    }
}
