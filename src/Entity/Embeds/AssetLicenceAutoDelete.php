<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Validator\Constraints as AppAssert;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
#[AppAssert\AssetLicenceAutoDeleteValid]
class AssetLicenceAutoDelete
{
    public const int MIN_OLDER_THAN_DAYS = 2;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Serialize]
    private bool $active = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Serialize]
    private int $olderThanDays = App::ZERO;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isNotActive(): bool
    {
        return false === $this->isActive();
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getOlderThanDays(): int
    {
        return $this->olderThanDays;
    }

    public function setOlderThanDays(int $olderThanDays): self
    {
        $this->olderThanDays = $olderThanDays;

        return $this;
    }
}
