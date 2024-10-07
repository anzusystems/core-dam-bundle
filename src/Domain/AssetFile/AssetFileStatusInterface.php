<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AssetFileStatusInterface
{
    public function store(AssetFile $assetFile, AdapterFile $file): AdapterFile;

    public function process(AssetFile $assetFile, AdapterFile $file, bool $dispatchPropertyRefresh): AssetFile;

    public function storeAndProcess(AssetFile $assetFile, ?AdapterFile $file = null, bool $dispatchPropertyRefresh = true): AssetFile;

    public static function getDefaultKeyName(): string;
}
