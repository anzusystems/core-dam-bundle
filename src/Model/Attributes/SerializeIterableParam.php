<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class SerializeIterableParam
{
    public function __construct(
        /**
         * @var class-string
         */
        public string $type,
        public ?int $maxItems = null,
    ) {
    }
}
