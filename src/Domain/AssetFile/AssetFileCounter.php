<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetFile;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetFileProcessStatus;
use AnzuSystems\CoreDamBundle\Repository\ChunkRepository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class AssetFileCounter
{
    private const string UPLOAD_SIZE_KEY = 'asset_file_uploaded_size';

    public function __construct(
        private readonly CacheItemPoolInterface $coreDamBundleCounterCache,
        private readonly ChunkRepository $chunkRepository,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function incrUploadedSize(AssetFile $assetFile, int $size): void
    {
        $item = $this->coreDamBundleCounterCache->getItem($this->getKey($assetFile));
        $item->set($this->getUploadedSizeFromCacheItem($item, $assetFile) + $size);
        $this->coreDamBundleCounterCache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function resetUploadedSize(AssetFile $assetFile): void
    {
        $item = $this->coreDamBundleCounterCache->getItem($this->getKey($assetFile));
        $item->set($this->getUploadedSizeFromCacheItem($item, $assetFile));
        $this->coreDamBundleCounterCache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getUploadedSize(AssetFile $assetFile): int
    {
        if ($assetFile->getAssetAttributes()->getStatus()->is(AssetFileProcessStatus::Uploading)) {
            $item = $this->coreDamBundleCounterCache->getItem($this->getKey($assetFile));

            return $this->getUploadedSizeFromCacheItem($item, $assetFile);
        }

        return $assetFile->getAssetAttributes()->getSize();
    }

    private function getUploadedSizeFromCacheItem(CacheItemInterface $item, AssetFile $assetFile): int
    {
        return $item->isHit()
            ? (int) $item->get()
            : $this->chunkRepository->getUploadedSizeByAssetFile($assetFile);
    }

    private function getKey(AssetFile $assetFile): string
    {
        return self::UPLOAD_SIZE_KEY . '_' . (string) $assetFile->getId();
    }
}
