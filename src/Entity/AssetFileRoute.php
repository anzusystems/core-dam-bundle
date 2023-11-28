<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UuidIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Embeds\AssetSlotFlags;
use AnzuSystems\CoreDamBundle\Entity\Traits\UuidIdentityTrait;
use AnzuSystems\CoreDamBundle\Exception\RuntimeException;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetSlotRepository;
use AnzuSystems\CoreDamBundle\Serializer\Handler\Handlers\AssetFileRouteLinksHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

#[ORM\Entity(repositoryClass: AssetSlotRepository::class)]
class AssetFileRoute implements UuidIdentifiableInterface, TimeTrackingInterface, UserTrackingInterface
{
    use UuidIdentityTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\OneToOne(mappedBy: 'route', targetEntity: AssetFile::class, fetch: App::DOCTRINE_EXTRA_LAZY)]
    protected AssetFile $assetFile;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Serialize]
    private string $slug;

    public function __construct()
    {
        $this->setPath('');
        $this->setSlug('');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getAssetFile(): AssetFile
    {
        return $this->assetFile;
    }

    public function setAssetFile(AssetFile $assetFile): self
    {
        $this->assetFile = $assetFile;

        return $this;
    }

    #[Serialize(handler: AssetFileRouteLinksHandler::class)]
    public function getLinks(): self
    {
        return $this;
    }
}
