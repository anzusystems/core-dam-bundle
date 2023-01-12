<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Audio\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Embeds\AudioPublicLink;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class AudioPublicLinkAdmDto
{
    private string $slug;
    private bool $public;

    public static function getInstance(AudioPublicLink $publicLink): self
    {
        return (new self())
            ->setSlug($publicLink->getSlug())
            ->setPublic($publicLink->isPublic());
    }

    #[Serialize]
    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    #[Serialize]
    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }
}
