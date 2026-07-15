<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Configuration;

use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;

final readonly class ExtSystemTtsConfiguration
{
    public const string KEY = 'tts';
    public const string ELEVENLABS_API_KEY = 'elevenlabs_api_key';
    public const string GOOGLE_CREDENTIALS_PATH = 'google_credentials_path';
    public const string CHUNK_STORAGE_NAME_KEY = 'chunk_storage_name';
    public const string OUTPUT_FORMAT_KEY = 'output_format';
    public const string DEFAULT_OUTPUT_FORMAT = 'mp3_44100_128';

    // 192k requires an ElevenLabs Creator+ tier; the whole pipeline is MP3-only.
    private const array OUTPUT_FORMAT_BITRATE = [
        'mp3_44100_128' => 128,
        'mp3_44100_192' => 192,
    ];

    public function __construct(
        public string $elevenlabsApiKey,
        public string $googleCredentialsPath,
        public string $chunkStorageName,
        public string $outputFormat,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function getFromArrayConfiguration(array $config): self
    {
        $outputFormat = (string) ($config[self::OUTPUT_FORMAT_KEY] ?? App::EMPTY_STRING);

        return new self(
            elevenlabsApiKey: (string) ($config[self::ELEVENLABS_API_KEY] ?? App::EMPTY_STRING),
            googleCredentialsPath: (string) ($config[self::GOOGLE_CREDENTIALS_PATH] ?? App::EMPTY_STRING),
            chunkStorageName: (string) ($config[self::CHUNK_STORAGE_NAME_KEY] ?? App::EMPTY_STRING),
            outputFormat: App::EMPTY_STRING === $outputFormat ? self::DEFAULT_OUTPUT_FORMAT : $outputFormat,
        );
    }

    /**
     * @throws TtsProviderException on an unsupported output_format
     */
    public function getOutputBitrateKbps(): int
    {
        return self::OUTPUT_FORMAT_BITRATE[$this->outputFormat]
            ?? throw new TtsProviderException(sprintf(
                'Unsupported TTS output_format "%s"; allowed: %s.',
                $this->outputFormat,
                implode(', ', array_keys(self::OUTPUT_FORMAT_BITRATE)),
            ));
    }
}
