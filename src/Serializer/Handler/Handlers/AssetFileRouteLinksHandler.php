<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Helper\UrlHelper;
use AnzuSystems\CoreDamBundle\Model\Configuration\AssetFileRouteConfigurableInterface;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class AssetFileRouteLinksHandler extends AbstractHandler
{
    private const LINKS_TYPE = 'public_route';

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
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

        if ($value instanceof AssetFile && $value->getRoute()) {
            return $this->getLinks($value->getRoute());
        }

        return [];
    }

    public function deserialize(mixed $value, Metadata $metadata): mixed
    {
        throw new SerializerException('deserialize_not_supported');
    }

    private function getLinks(AssetFileRoute $route): array
    {
        $assetFile = $route->getAssetFile();
        $config = $this->extSystemConfigurationProvider->getExtSystemConfigurationByAsset($assetFile->getAsset());

        if ($config instanceof AssetFileRouteConfigurableInterface) {
            return [
                'type' => $assetFile->getAssetType()->toString(),
                'url' => UrlHelper::concatPathWithDomain(
                    $config->getPublicDomainName(),
                    $route->getPath()
                ),
            ];
        }

        return [];
    }
}
