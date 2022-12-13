<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetMetadata\IndexBuilder;

use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface IndexBuilderInterface
{
    public static function getDefaultKeyName(): string;

    public function getIndexDefinition(CustomFormElement $element): array;
}
