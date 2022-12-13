<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmDetailDto;

final class DocumentUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/document/licence/1";
    }

    public function getCreateChunkPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/document/{$assetId}/chunk";
    }

    public function getFinishUploadPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/document/{$assetId}/uploaded";
    }

    public function getSingleAssetPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/document/{$assetId}";
    }

    public function getAddToPositionPath(string $assetId, string $position): string
    {
        return "/api/adm/v{$this->version}/document/asset/{$assetId}/position/$position";
    }

    public function getSerializeClassString(): string
    {
        return DocumentFileAdmDetailDto::class;
    }
}
