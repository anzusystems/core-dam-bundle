<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile\FileProcessor;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AssetFileProcessorInterface
{
    public function process(AssetFile $assetFile): void;

    public static function getDefaultKeyName(): string;
}
