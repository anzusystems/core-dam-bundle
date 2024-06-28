<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class JwDistributionServiceConfiguration extends DistributionServiceConfiguration
{
    public const string SECRET_V2_KEY = 'secret_v2';
    public const string SITE_ID_KEY = 'site_id';

    private string $secretV2;
    private string $siteId;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setSecretV2($config[parent::OPTIONS_KEY][self::SECRET_V2_KEY] ?? '')
            ->setSiteId($config[parent::OPTIONS_KEY][self::SITE_ID_KEY] ?? '')
        ;
    }

    public function getSecretV2(): string
    {
        return $this->secretV2;
    }

    public function setSecretV2(string $secretV2): self
    {
        $this->secretV2 = $secretV2;

        return $this;
    }

    public function getSiteId(): string
    {
        return $this->siteId;
    }

    public function setSiteId(string $siteId): self
    {
        $this->siteId = $siteId;

        return $this;
    }
}
