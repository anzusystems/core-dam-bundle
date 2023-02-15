<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemImageTypeConfiguration extends ExtSystemAssetTypeConfiguration
{
    public const PUBLIC_DOMAIN_KEY = 'public_domain';
    public const ADMIN_DOMAIN_KEY = 'admin_domain';
    public const ROI_WIDTH_KEY = 'roi_width';
    public const ROI_HEIGHT_KEY = 'roi_height';
    public const CROP_STORAGE_NAME = 'crop_storage_name';

    private int $roiWidth;
    private int $roiHeight;
    private string $cropStorageName;
    private string $publicDomain;
    private string $adminDomain;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setAdminDomain($config[self::ADMIN_DOMAIN_KEY] ?? '')
            ->setPublicDomain($config[self::PUBLIC_DOMAIN_KEY] ?? '')
            ->setRoiWidth($config[self::ROI_WIDTH_KEY] ?? 0)
            ->setRoiHeight($config[self::ROI_HEIGHT_KEY] ?? 0)
            ->setCropStorageName($config[self::CROP_STORAGE_NAME] ?? '');
    }

    public function getPublicDomain(): string
    {
        return $this->publicDomain;
    }

    public function setPublicDomain(string $publicDomain): self
    {
        $this->publicDomain = $publicDomain;

        return $this;
    }

    public function getAdminDomain(): string
    {
        return $this->adminDomain;
    }

    public function setAdminDomain(string $adminDomain): self
    {
        $this->adminDomain = $adminDomain;

        return $this;
    }

    public function getCropStorageName(): string
    {
        return $this->cropStorageName;
    }

    public function setCropStorageName(string $cropStorageName): self
    {
        $this->cropStorageName = $cropStorageName;

        return $this;
    }

    public function getRoiWidth(): int
    {
        return $this->roiWidth;
    }

    public function setRoiWidth(int $roiWidth): self
    {
        $this->roiWidth = $roiWidth;

        return $this;
    }

    public function getRoiHeight(): int
    {
        return $this->roiHeight;
    }

    public function setRoiHeight(int $roiHeight): self
    {
        $this->roiHeight = $roiHeight;

        return $this;
    }
}
