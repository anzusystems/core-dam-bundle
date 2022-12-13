<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class YoutubeDistributionServiceConfiguration extends DistributionServiceConfiguration
{
    public const OAUTH_CREDENTIALS_KEY = 'oauth_credentials';
    public const CHANNEL_ID_KEY = 'channel_id';
    public const REGION_CODE = 'region_code';

    private string $oauthCredentials;
    private string $channelId;
    private string $regionCode;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setOauthCredentials($config[parent::OPTIONS_KEY][self::OAUTH_CREDENTIALS_KEY] ?? '')
            ->setChannelId($config[parent::OPTIONS_KEY][self::CHANNEL_ID_KEY] ?? '')
            ->setRegionCode($config[parent::OPTIONS_KEY][self::REGION_CODE] ?? '')
        ;
    }

    public function getOauthCredentials(): string
    {
        return $this->oauthCredentials;
    }

    public function setOauthCredentials(string $oauthCredentials): self
    {
        $this->oauthCredentials = $oauthCredentials;

        return $this;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): self
    {
        $this->channelId = $channelId;

        return $this;
    }

    public function getRegionCode(): string
    {
        return $this->regionCode;
    }

    public function setRegionCode(string $regionCode): self
    {
        $this->regionCode = $regionCode;

        return $this;
    }
}
