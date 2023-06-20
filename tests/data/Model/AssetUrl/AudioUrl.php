<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl;

use AnzuSystems\CoreDamBundle\Model\Dto\Audio\AudioFileAdmDetailDto;

final class AudioUrl extends AbstractAssetFileUrl
{
    public function getCreatePath(): string
    {
        return "/api/adm/v{$this->version}/audio/licence/{$this->licenceId}";
    }

    public function getCreateChunkPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/audio/{$assetId}/chunk";
    }

    public function getFinishUploadPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/audio/{$assetId}/uploaded";
    }

    public function getSingleAssetPath(string $assetId): string
    {
        return "/api/adm/v{$this->version}/audio/{$assetId}";
    }

    public function setToSlot(string $assetId, string $assetFileId, string $position): string
    {
        return "/api/adm/v{$this->version}/audio/{$assetFileId}/asset/{$assetId}/slot-name/{$position}";
    }

    public function setMainFilePath(string $assetId, string $imageId): string
    {
        return "/api/adm/v{$this->version}/audio/{$imageId}/asset/{$assetId}/main";
    }

    public function getAddToSlotPath(string $assetId, string $slotName): string
    {
        return "/api/adm/v{$this->version}/audio/asset/{$assetId}/slot-name/$slotName";
    }

    public function getSerializeClassString(): string
    {
        return AudioFileAdmDetailDto::class;
    }
}
