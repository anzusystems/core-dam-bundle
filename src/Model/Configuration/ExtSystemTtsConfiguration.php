<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\App;

/** Per-ExtSystem TTS credentials (ElevenLabs + Google) and chunk storage name for multi-chunk synthesis. */
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
