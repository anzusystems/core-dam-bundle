<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\File\File;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AssetFileStatusInterface
{
    public function store(AssetFile $assetFile, ?File $file): File;

    public function process(AssetFile $assetFile, File $file): AssetFile;

    public function storeAndProcess(AssetFile $assetFile, ?File $file): AssetFile;

    public static function getDefaultKeyName(): string;
}
