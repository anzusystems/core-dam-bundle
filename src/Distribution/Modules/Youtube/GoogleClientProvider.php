<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use Google\Exception;
use Google_Client;
use Google_Service_YouTube;

final class GoogleClientProvider
{
    public const REQUIRED_SCOPES = [
        Google_Service_YouTube::YOUTUBE_UPLOAD,
        Google_Service_YouTube::YOUTUBE_READONLY,
        Google_Service_YouTube::YOUTUBEPARTNER,
    ];

    private const ACCESS_TYPE = 'offline';
    private const PROMPT = 'consent';

    public function __construct(
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
        private readonly ConfigurationProvider $configurationProvider,
        private array $clientCache = [],
    ) {
    }

    /**
     * @throws Exception
     */
    public function getClient(string $distributionService): Google_Client
    {
        return $this->clientCache[$distributionService] ??= $this->createClient($distributionService);
    }

    public function getKeyClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setDeveloperKey($this->configurationProvider->getSettings()->getYoutubeApiKey());

        return $client;
    }

    /**
     * @throws DomainException
     * @throws Exception
     */
    private function createClient(string $distributionService): Google_Client
    {
        $configuration = $this->distributionConfigurationProvider->getYoutubeDistributionService($distributionService);

        $client = new Google_Client();
        $client->setAuthConfig($configuration->getOauthCredentials());
        $client->setAccessType(self::ACCESS_TYPE);
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt(self::PROMPT);

        foreach (self::REQUIRED_SCOPES as $scope) {
            $client->addScope($scope);
        }

        $client->setRedirectUri($configuration->getRedirectUri());

        return $client;
    }
}
