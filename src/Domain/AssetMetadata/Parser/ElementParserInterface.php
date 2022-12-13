<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\Parser;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ElementParserInterface
{
    public static function getDefaultKeyName(): string;

    public function parse(CustomFormElement $element, mixed $value): mixed;
}
