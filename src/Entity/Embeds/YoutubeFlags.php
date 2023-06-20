<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class YoutubeFlags
{
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $embeddable;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $forKids;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Serialize]
    private bool $notifySubscribers;

    public function __construct()
    {
        $this->setEmbeddable(true);
        $this->setForKids(false);
        $this->setNotifySubscribers(false);
    }

    public function isEmbeddable(): bool
    {
        return $this->embeddable;
    }

    public function setEmbeddable(bool $embeddable): self
    {
        $this->embeddable = $embeddable;

        return $this;
    }

    public function isForKids(): bool
    {
        return $this->forKids;
    }

    public function setForKids(bool $forKids): self
    {
        $this->forKids = $forKids;

        return $this;
    }

    public function isNotifySubscribers(): bool
    {
        return $this->notifySubscribers;
    }

    public function setNotifySubscribers(bool $notifySubscribers): self
    {
        $this->notifySubscribers = $notifySubscribers;

        return $this;
    }
}
