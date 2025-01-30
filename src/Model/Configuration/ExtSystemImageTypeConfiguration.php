<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class ExtSystemImageTypeConfiguration extends ExtSystemAssetTypeConfiguration implements AssetFileRouteConfigurableInterface
{
    public const string PUBLIC_DOMAIN_KEY = 'public_domain';
    public const string PUBLIC_DOMAIN_NAME_KEY = 'public_domain_name';
    public const string ADMIN_DOMAIN_KEY = 'admin_domain';
    public const string ADMIN_DOMAIN_NAME_KEY = 'admin_domain_name';
    public const string ROI_WIDTH_KEY = 'roi_width';
    public const string ROI_HEIGHT_KEY = 'roi_height';
    public const string CROP_STORAGE_NAME = 'crop_storage_name';
    public const string NOT_FOUND_IMAGE_ID = 'not_found_image_id';

    private int $roiWidth;
    private int $roiHeight;
    private string $cropStorageName;
    private string $publicDomain;
    private string $publicDomainName;
    private string $adminDomain;
    private string $adminDomainName;
    private string $notFoundImageId;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setAdminDomain($config[self::ADMIN_DOMAIN_KEY] ?? '')
            ->setPublicDomain($config[self::PUBLIC_DOMAIN_KEY] ?? '')
            ->setRoiWidth($config[self::ROI_WIDTH_KEY] ?? 0)
            ->setRoiHeight($config[self::ROI_HEIGHT_KEY] ?? 0)
            ->setCropStorageName($config[self::CROP_STORAGE_NAME] ?? '')
            ->setPublicDomainName($config[self::PUBLIC_DOMAIN_NAME_KEY] ?? '')
            ->setAdminDomainName($config[self::ADMIN_DOMAIN_NAME_KEY] ?? '')
        ;
    }

    public function getPublicDomainName(): string
    {
        return $this->publicDomainName;
    }

    public function setPublicDomainName(string $publicDomainName): static
    {
        $this->publicDomainName = $publicDomainName;

        return $this;
    }

    public function getAdminDomainName(): string
    {
        return $this->adminDomainName;
    }

    public function setAdminDomainName(string $adminDomainName): static
    {
        $this->adminDomainName = $adminDomainName;

        return $this;
    }

    public function getPublicDomain(): string
    {
        return $this->publicDomain;
    }

    public function setPublicDomain(string $publicDomain): static
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

    public function getNotFoundImageId(): string
    {
        return $this->notFoundImageId;
    }

    public function setNotFoundImageId(string $notFoundImageId): self
    {
        $this->notFoundImageId = $notFoundImageId;

        return $this;
    }
}
