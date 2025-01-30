<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Traits;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait ExportTypePositionTrait
{
    #[ORM\Column(type: Types::INTEGER, options: [
        'unsigned' => true,
        'default' => App::ZERO,
    ])]
    #[Serialize]
    private int $webOrderPosition = App::ZERO;

    #[ORM\Column(type: Types::INTEGER, options: [
        'unsigned' => true,
        'default' => App::ZERO,
    ])]
    #[Serialize]
    private int $mobileOrderPosition = App::ZERO;

    public function getWebOrderPosition(): int
    {
        return $this->webOrderPosition;
    }

    public function setWebOrderPosition(int $webOrderPosition): self
    {
        $this->webOrderPosition = $webOrderPosition;

        return $this;
    }

    public function getMobileOrderPosition(): int
    {
        return $this->mobileOrderPosition;
    }

    public function setMobileOrderPosition(int $mobileOrderPosition): self
    {
        $this->mobileOrderPosition = $mobileOrderPosition;

        return $this;
    }
}
