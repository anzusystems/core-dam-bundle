<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Video\VideoFileAdmDetailDto;

final class VideoUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/video/licence/1";
    }

    public function getCreateChunkPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/video/{$assetId}/chunk";
    }

    public function getFinishUploadPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/video/{$assetId}/uploaded";
    }

    public function getSingleAssetPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/video/{$assetId}";
    }

    public function getAddToPositionPath(string $assetId, string $position): string
    {
        return "/api/adm/v{$this->version}/video/asset/{$assetId}/position/$position";
    }

    public function getSerializeClassString(): string
    {
        return VideoFileAdmDetailDto::class;
    }
}
