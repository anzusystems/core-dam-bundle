<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Traits\ExportTypePositionTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class VideoShowAttributes
{
    use ExportTypePositionTrait;
}
