<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;

final class ImageUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/image/licence/1";
    }

    public function getCreateChunkPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/image/{$assetId}/chunk";
    }

    public function getFinishUploadPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/image/{$assetId}/uploaded";
    }

    public function getSingleAssetPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/image/{$assetId}";
    }

    public function getAddToPositionPath(string $assetId, string $position): string
    {
        return "/api/adm/v{$this->version}/image/asset/{$assetId}/position/$position";
    }

    public function getSerializeClassString(): string
    {
        return ImageFileAdmDetailDto::class;
    }
}
