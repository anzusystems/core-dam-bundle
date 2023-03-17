<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Document\DocumentFileAdmDetailDto;

final class DocumentUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/document/licence/{$this->licenceId}";
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

    public function getAddToSlotPath(string $assetId, string $slotName): string
    {
        return "/api/adm/v{$this->version}/document/asset/{$assetId}/slot-name/$slotName";
    }

    public function setToSlot(string $assetId, string $assetFileId, string $position): string
    {
        return "/api/adm/v{$this->version}/document/{$assetFileId}/asset/{$assetId}/slot-name/{$position}";
    }

    public function setMainFilePath(string $assetId, string $imageId): string
    {
        return "/api/adm/v{$this->version}/document/{$imageId}/asset/{$assetId}/main";
    }

    public function getSerializeClassString(): string
    {
        return DocumentFileAdmDetailDto::class;
    }
}
