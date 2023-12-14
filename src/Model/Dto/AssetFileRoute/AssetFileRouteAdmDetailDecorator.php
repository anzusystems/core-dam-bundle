<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\AssetFileRoute;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\AssetFileRoute;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileRouteUrlHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;

final class AssetFileRouteAdmDetailDecorator extends AbstractEntityDto
{
    #[Serialize(serializedName: 'id', handler: EntityIdHandler::class)]
    private AssetFileRoute $assetFileRoute;

    public static function getInstance(
       AssetFileRoute $assetFileRoute
    ): static {
        return self::getBaseInstance($assetFileRoute)->setAssetFileRoute($assetFileRoute);
    }

    public function getAssetFileRoute(): AssetFileRoute
    {
        return $this->assetFileRoute;
    }

    public function setAssetFileRoute(AssetFileRoute $assetFileRoute): self
    {
        $this->assetFileRoute = $assetFileRoute;
        return $this;
    }

    #[Serialize]
    public function getStatus(): RouteStatus
    {
        return $this->assetFileRoute->getStatus();
    }

    #[Serialize]
    public function isMain(): bool
    {
        return $this->assetFileRoute->getUri()->isMain();
    }

    #[Serialize(handler: AssetFileRouteUrlHandler::class)]
    public function getPublicUrl(): AssetFileRoute
    {
        return $this->assetFileRoute;
    }
}
