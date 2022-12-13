<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

interface AssetUrlInterface
{
    public function getCreatePath(): string;
    public function getCreateChunkPath(string $assetId): string;
    public function getFinishUploadPath(string $assetId): string;
    public function getSingleAssetPath(string $assetId): string;
    public function getAddToPositionPath(string $assetId, string $position): string;
    public function getSerializeClassString(): string;
}
