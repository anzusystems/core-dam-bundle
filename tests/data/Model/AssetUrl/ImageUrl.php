<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Image\ImageFileAdmDetailDto;

final class ImageUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/image/licence/{$this->licenceId}";
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

    public function getAddToSlotPath(string $assetId, string $slotName): string
    {
        return "/api/adm/v{$this->version}/image/asset/{$assetId}/slot-name/$slotName";
    }

    public function setToSlot(string $assetId, string $assetFileId, string $position): string
    {
        return "/api/adm/v{$this->version}/image/{$assetFileId}/asset/{$assetId}/slot-name/{$position}";
    }

    public function setMainFilePath(string $assetId, string $imageId): string
    {
        return "/api/adm/v{$this->version}/image/{$imageId}/asset/{$assetId}/main";
    }

    public function copy(): string
    {
        return "/api/adm/v{$this->version}/image/copy-to-licence";
    }

    public function getSerializeClassString(): string
    {
        return ImageFileAdmDetailDto::class;
    }
}
