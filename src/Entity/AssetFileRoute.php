<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioAttributes;
use AnzuSystems\CoreDamBundle\Entity\Embeds\RouteUri;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteMode;
use AnzuSystems\CoreDamBundle\Model\Enum\RouteStatus;
use AnzuSystems\CoreDamBundle\Repository\AssetFileRouteRepository;
use AnzuSystems\CoreDamBundle\Repository\AssetSlotRepository;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileRouteLinksHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssetFileRouteRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_uri_path', fields: ['uri_path'])]
#[ORM\Index(fields: ['uri_main, target_asset_file'], name: 'IDQ_main_asset_file_id')]
class AssetFileRoute implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\ManyToOne(targetEntity: AssetFile::class, fetch: App::DOCTRINE_EXTRA_LAZY, inversedBy: 'routes')]
    #[ORM\JoinColumn(nullable: false)]
    private AssetFile $targetAssetFile;

    #[ORM\Embedded(class: RouteUri::class)]
    #[Serialize]
    private RouteUri $uri;

    #[ORM\Column(enumType: RouteStatus::class)]
    #[Serialize]
    private RouteStatus $status;

    #[ORM\Column(enumType: RouteMode::class)]
    #[Serialize]
    private RouteMode $mode;

    public function __construct()
    {
        $this->setUri(new RouteUri());
        $this->setStatus(RouteStatus::Default);
        $this->setMode(RouteMode::Default);
    }

    public function getTargetAssetFile(): AssetFile
    {
        return $this->targetAssetFile;
    }

    public function setTargetAssetFile(AssetFile $targetAssetFile): self
    {
        $this->targetAssetFile = $targetAssetFile;
        return $this;
    }

    public function getUri(): RouteUri
    {
        return $this->uri;
    }

    public function setUri(RouteUri $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    public function getStatus(): RouteStatus
    {
        return $this->status;
    }

    public function setStatus(RouteStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMode(): RouteMode
    {
        return $this->mode;
    }

    public function setMode(RouteMode $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    #[Serialize(handler: AssetFileRouteLinksHandler::class)]
    public function getLinks(): self
    {
        return $this;
    }
}
