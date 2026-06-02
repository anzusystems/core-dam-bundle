<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\App;

/**
 * Per-ExtSystem TTS provider credentials. Holds tenant-specific keys for ElevenLabs + Google TTS;
 * lookup happens at synthesis time via {@see \AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider}.
 *
 * `chunkStorageName` names the AnzuSystemsCoreDam storage (defined in app `storages.yaml`) where
 * per-chunk MP3 blobs are persisted during multi-chunk synthesis. Per-extSystem to respect tenant
 * data separation (legal isolation). Mirrors {@see ExtSystemAssetTypeConfiguration::$chunkStorageName}
 * shape used for upload chunks.
 */
final readonly class ExtSystemTtsConfiguration
{
    public const string KEY = 'tts';
    public const string ELEVENLABS_API_KEY = 'elevenlabs_api_key';
    public const string GOOGLE_CREDENTIALS_PATH = 'google_credentials_path';
    public const string CHUNK_STORAGE_NAME_KEY = 'chunk_storage_name';

    public function __construct(
        public string $elevenlabsApiKey,
        public string $googleCredentialsPath,
        public string $chunkStorageName,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function getFromArrayConfiguration(array $config): self
    {
        return new self(
            elevenlabsApiKey: (string) ($config[self::ELEVENLABS_API_KEY] ?? App::EMPTY_STRING),
            googleCredentialsPath: (string) ($config[self::GOOGLE_CREDENTIALS_PATH] ?? App::EMPTY_STRING),
            chunkStorageName: (string) ($config[self::CHUNK_STORAGE_NAME_KEY] ?? App::EMPTY_STRING),
        );
    }
}
