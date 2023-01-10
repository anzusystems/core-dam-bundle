<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Validator\Constraints;

use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
final class AssetProperties extends Constraint
{
    public function __construct(
        public ?AssetType $assetType = null,
        array $options = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
