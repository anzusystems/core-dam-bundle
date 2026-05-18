<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient;

use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use Google\Client as GoogleClient;
use Google\Exception as GoogleException;

/**
 * Per-ExtSystem cache of {@see GoogleClient} instances pre-configured with service-account
 * credentials. Mirrors {@see \AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\GoogleClientProvider}
 * for the TTS scope — keeps JSON keyfile parsing + scope setup out of the hot path.
 *
 * Each Google_Client owns its own token cache (set internally by `fetchAccessTokenWithAssertion`)
 * so callers get token reuse for free.
 */
final class GoogleTtsAuthClientProvider
{
    public const string SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /** @var array<string, GoogleClient> keyed by ExtSystem slug */
    private array $cache = [];

    public function __construct(
        private readonly ExtSystemConfigurationProvider $extSystemConfigProvider,
    ) {
    }

    /**
     * @throws TtsProviderException
     */
    public function getClient(string $extSystemSlug): GoogleClient
    {
        return $this->cache[$extSystemSlug] ??= $this->buildClient($extSystemSlug);
    }

    /**
     * @throws TtsProviderException
     */
    private function buildClient(string $extSystemSlug): GoogleClient
    {
        $credentialsPath = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystemSlug)->googleCredentialsPath;
        if ('' === $credentialsPath) {
            throw new TtsProviderException(sprintf(
                'No Google TTS credentials path configured for ExtSystem "%s".',
                $extSystemSlug,
            ));
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(self::SCOPE);
        } catch (GoogleException $e) {
            throw new TtsProviderException(sprintf(
                'Google TTS auth init failed for ExtSystem "%s": %s',
                $extSystemSlug,
                $e->getMessage(),
            ), 0, $e);
        }

        return $client;
    }
}
