<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class JobImageDefaultSource extends Job
{
    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    private int $extSystemId = App::ZERO;

    public function getExtSystemId(): int
    {
        return $this->extSystemId;
    }

    public function setExtSystemId(int $extSystemId): self
    {
        $this->extSystemId = $extSystemId;

        return $this;
    }
}
