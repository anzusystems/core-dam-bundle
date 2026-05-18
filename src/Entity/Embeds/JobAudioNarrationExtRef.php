<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * External-system reference triple ({@see extResourceName}, {@see extId}, {@see extVersion}).
 * The (resourceName, id) pair is the idempotency tuple per {@see ExtSystem}.
 */
#[ORM\Embeddable]
class JobAudioNarrationExtRef
{
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $extResourceName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serialize]
    private ?string $extId;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Serialize]
    private ?string $extVersion;

    public function __construct()
    {
        $this->setExtResourceName(null);
        $this->setExtId(null);
        $this->setExtVersion(null);
    }

    public function getExtResourceName(): ?string
    {
        return $this->extResourceName;
    }

    public function setExtResourceName(?string $extResourceName): self
    {
        $this->extResourceName = $extResourceName;

        return $this;
    }

    public function getExtId(): ?string
    {
        return $this->extId;
    }

    public function setExtId(?string $extId): self
    {
        $this->extId = $extId;

        return $this;
    }

    public function getExtVersion(): ?string
    {
        return $this->extVersion;
    }

    public function setExtVersion(?string $extVersion): self
    {
        $this->extVersion = $extVersion;

        return $this;
    }
}
