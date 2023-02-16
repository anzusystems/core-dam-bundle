<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class CropAllowListConfiguration
{
    public const QUALITY_ALLOW_LIST = 'quality_whitelist';
    public const DOMAIN = 'domain';
    public const CROPS = 'crops';

    private string $domain;
    private array $qualityAllowList;
    private array $crops;

    public static function getFromArrayConfiguration(array $config): self
    {
        return (new self())
            ->setQualityAllowList($config[self::QUALITY_ALLOW_LIST] ?? [])
            ->setDomain($config[self::DOMAIN] ?? '')
            ->setCrops($config[self::CROPS] ?? []);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getQualityAllowList(): array
    {
        return $this->qualityAllowList;
    }

    public function setQualityAllowList(array $qualityAllowList): self
    {
        $this->qualityAllowList = $qualityAllowList;

        return $this;
    }

    /**
     * @return list<array{width: int, height: int, tags: list<string>, title: string}>
     */
    public function getCrops(): array
    {
        return $this->crops;
    }

    public function setCrops(array $crops): self
    {
        $this->crops = $crops;

        return $this;
    }
}
