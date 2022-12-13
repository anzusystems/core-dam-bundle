<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Entity\Embeds;

use AnzuSystems\CoreDamBundle\Entity\Traits\ReviewedTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class KeywordFlags
{
    use ReviewedTrait;
}
