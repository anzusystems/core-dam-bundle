<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Asset;

use AnzuSystems\CoreDamBundle\Entity\Asset;
use Doctrine\ORM\NonUniqueResultException;

final class AssetPropertyAccessor
{
    private const TARGET_CUSTOM_DATA = 'customData';
    private const TARGET_FILE_ATTRIBUTES = 'assetFileAttributes';
    private const TARGET_ASSET = 'asset';
    private const TARGET_ASSET_FILE = 'assetFile';
    private const TARGET_ASSET_TEXTS = 'assetTexts';

    /**
     * @param array<int, string> $configs
     *
     * @throws NonUniqueResultException
     */
    public function getPropertyValue(Asset $asset, array $configs): string
    {
        foreach ($configs as $config) {
            [$target, $key] = explode(':', $config);

            $value = $this->getValue($asset, $target, $key);
            if (false === empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @throws NonUniqueResultException
     */
    private function getValue(Asset $asset, string $target, string $key): string
    {
        if (self::TARGET_CUSTOM_DATA === $target) {
            return $this->getFromCustomData($asset, $key);
        }
        if (self::TARGET_FILE_ATTRIBUTES === $target) {
            return $this->getFromFileAttributes($asset, $key);
        }
        if (self::TARGET_ASSET_FILE === $target) {
            return $this->getFromAssetFile($asset, $key);
        }
        if (self::TARGET_ASSET === $target) {
            return $this->getFromAsset($asset, $key);
        }
        if (self::TARGET_ASSET_TEXTS === $target) {
            return $this->getFromAssetTexts($asset, $key);
        }

        return '';
    }

    private function getFromCustomData(Asset $asset, string $key): string
    {
        return $asset->getMetadata()->getCustomData()[$key] ?? '';
    }

    /**
     * @throws NonUniqueResultException
     */
    private function getFromFileAttributes(Asset $asset, string $key): string
    {
        if (null === $asset->getMainFile()) {
            return '';
        }

        $getter = 'get' . ucfirst($key);
        if (method_exists($asset->getMainFile()->getAssetAttributes(), $getter)) {
            return (string) $asset->getMainFile()->getAssetAttributes()->{$getter}();
        }

        return '';
    }

    /**
     * @throws NonUniqueResultException
     */
    private function getFromAssetFile(Asset $asset, string $key): string
    {
        if (null === $asset->getMainFile()) {
            return '';
        }

        $getter = 'get' . ucfirst($key);
        if (method_exists($asset->getMainFile(), $getter)) {
            return (string) $assetFile->{$getter}();
        }

        return '';
    }

    private function getFromAsset(Asset $asset, string $key): string
    {
        $getter = 'get' . ucfirst($key);
        if (method_exists($asset, $getter)) {
            return (string) $asset->{$getter}();
        }

        return '';
    }

    private function getFromAssetTexts(Asset $asset, string $key): string
    {
        $getter = 'get' . ucfirst($key);
        if (method_exists($asset->getTexts(), $getter)) {
            return (string) $asset->getTexts()->{$getter}();
        }

        return '';
    }
}
