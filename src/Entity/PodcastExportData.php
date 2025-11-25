<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityIntTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Model\Enum\DeviceType;
use AnzuSystems\CoreDamBundle\Model\Enum\ExportType;
use AnzuSystems\CoreDamBundle\Repository\PodcastExportDataRepository;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PodcastExportDataRepository::class)]
class PodcastExportData implements TimeTrackingInterface, UserTrackingInterface, IdentifiableInterface
{
    use IdentityIntTrait;
    use UserTrackingTrait;
    use TimeTrackingTrait;

    #[ORM\Column(enumType: ExportType::class)]
    #[Serialize]
    private ExportType $exportType;

    #[ORM\Column(enumType: DeviceType::class)]
    #[Serialize]
    private DeviceType $deviceType;

    #[ORM\ManyToOne(targetEntity: Podcast::class, fetch: App::DOCTRINE_EXTRA_LAZY, inversedBy: 'exportData')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Serialize(handler: EntityIdHandler::class)]
    private Podcast $podcast;

    #[ORM\Column(type: Types::JSON)]
    #[Serialize]
    private array $body;

    public function __construct()
    {
        $this->setExportType(ExportType::Default);
        $this->setDeviceType(DeviceType::Default);
        $this->setBody([]);
    }

    public function getExportType(): ExportType
    {
        return $this->exportType;
    }

    public function setExportType(ExportType $exportType): self
    {
        $this->exportType = $exportType;

        return $this;
    }

    public function getDeviceType(): DeviceType
    {
        return $this->deviceType;
    }

    public function setDeviceType(DeviceType $deviceType): self
    {
        $this->deviceType = $deviceType;

        return $this;
    }

    public function getPodcast(): Podcast
    {
        return $this->podcast;
    }

    public function setPodcast(Podcast $podcast): self
    {
        $this->podcast = $podcast;

        return $this;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function setBody(array $body): self
    {
        $this->body = $body;

        return $this;
    }
}
