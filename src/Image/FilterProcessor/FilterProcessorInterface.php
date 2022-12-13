<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Image\FilterProcessor;

use AnzuSystems\CoreDamBundle\Image\Filter\ImageFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface FilterProcessorInterface
{
    public function supportsFilter(): string;

    public static function getDefaultKeyName(): string;

    public function applyFilter(ImageFilterInterface $filter): void;
}
