<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Cache\AssetFileRouteGenerator;
use AnzuSystems\CoreDamBundle\Domain\AssetFileRoute\AssetFileRouteManager;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetFileRouteLinksHandler extends AbstractHandler
{
    private const LINKS_TYPE = 'public_route';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
        private readonly AssetFileRouteRepository $assetFileRouteRepository,
        private readonly AssetFileRouteGenerator $assetFileRouteGenerator,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata): mixed
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof AssetFileRoute) {
            return $this->getLinks($value);
        }

        if ($value instanceof AssetFile) {
            $route = $this->assetFileRouteRepository->findMainByAssetFile((string) $value->getId());

            if ($route) {
                return $this->getLinks($route);
            }
        }

        return [];
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    private function getLinks(AssetFileRoute $route): array
    {
        $url = $this->assetFileRouteGenerator->getFullUrl($route);
        if (false === empty($url)) {
            return [
                'type' => $route->getTargetAssetFile()->getAssetType()->toString(),
                'url' => $url,
            ];
        }

        return [];
    }
}
