<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\App;

/**
 * Per-ExtSystem TTS provider credentials. Holds tenant-specific keys for ElevenLabs + Google TTS;
 * lookup happens at synthesis time via {@see \AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider}.
 *
 * Podcast IDs are stored on ExtSystemTtsSettings (ORM embedded) — not in YAML config.
 */
final readonly class ExtSystemTtsConfiguration
{
    public const string KEY = 'tts';
    public const string ELEVENLABS_API_KEY = 'elevenlabs_api_key';
    public const string GOOGLE_CREDENTIALS_PATH = 'google_credentials_path';
    public const string LANGUAGE_CODE = 'language_code';

    public function __construct(
        public string $elevenlabsApiKey,
        public string $googleCredentialsPath,
        public string $languageCode,
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
            languageCode: (string) ($config[self::LANGUAGE_CODE] ?? App::EMPTY_STRING),
        );
    }
}
