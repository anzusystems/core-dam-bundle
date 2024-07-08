<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

final class YoutubeDistributionServiceConfiguration extends DistributionServiceConfiguration
{
    public const string OAUTH_CREDENTIALS_KEY = 'oauth_credentials';
    public const string CHANNEL_ID_KEY = 'channel_id';
    public const string REGION_CODE = 'region_code';
    public const string REDIRECT_URI = 'redirect_uri';
    public const string YOUTUBE_DEFAULT_LANGUAGE = 'default_language';

    private string $oauthCredentials;
    private string $channelId;
    private string $regionCode;
    private string $redirectUri;
    private string $defaultLanguage;

    public static function getFromArrayConfiguration(array $config): static
    {
        return parent::getFromArrayConfiguration($config)
            ->setOauthCredentials($config[parent::OPTIONS_KEY][self::OAUTH_CREDENTIALS_KEY] ?? '')
            ->setChannelId($config[parent::OPTIONS_KEY][self::CHANNEL_ID_KEY] ?? '')
            ->setRegionCode($config[parent::OPTIONS_KEY][self::REGION_CODE] ?? '')
            ->setRedirectUri($config[parent::OPTIONS_KEY][self::REDIRECT_URI] ?? '')
            ->setDefaultLanguage($config[parent::OPTIONS_KEY][self::YOUTUBE_DEFAULT_LANGUAGE] ?? '')
        ;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;

        return $this;
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

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }
}
