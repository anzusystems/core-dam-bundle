<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Traits\ExportTypeEnableTrait;
use AnzuSystems\CoreDamBundle\Entity\Traits\ExportTypePositionTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class VideoShowEpisodeAttributes
{
    use ExportTypePositionTrait;
}
