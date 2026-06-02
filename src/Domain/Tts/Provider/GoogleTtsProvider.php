<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\Config;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsAuthClientProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsClient;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Generator;
use Google\Exception as GoogleException;
use JsonException;
use League\Flysystem\FilesystemException;

/**
 * Google Cloud TTS. Service-account auth + token caching delegated to {@see GoogleTtsAuthClientProvider}
 * (per-ExtSystem GoogleClient cache, JSON keyfile parsed once). Long text chunked + concatenated via ffmpeg.
 */
final class GoogleTtsProvider extends AbstractTtsProvider
{
    // Google's hard documented per-request ceiling. The effective chunk size is the operator-driven
    // Config::chunkSizeChars clamped to this — see AbstractTtsProvider::resolveChunkSize().
    // Intentionally independent of ElevenlabsTtsProvider::MAX_CHARS — do not merge into a shared constant.
    private const int MAX_CHARS = 5_000;
    private const string AUDIO_ENCODING_MP3 = 'MP3';
    private const string RESPONSE_KEY_AUDIO_CONTENT = 'audioContent';
    private const string RESPONSE_KEY_ACCESS_TOKEN = 'access_token';

    public function __construct(
        private readonly GoogleTtsClient $ttsClient,
        private readonly GoogleTtsAuthClientProvider $authClientProvider,
        private readonly TextChunker $chunker,
        ExtSystemConfigurationProvider $extSystemConfigProvider,
        FileSystemProvider $fileSystemProvider,
        FfmpegService $ffmpegService,
        Config $config,
    ) {
        parent::__construct($fileSystemProvider, $ffmpegService, $extSystemConfigProvider, $config);
    }

    public static function getDefaultKeyName(): string
    {
        return VoiceDiscriminator::GoogleTts->value;
    }

    public function getName(): VoiceDiscriminator
    {
        return VoiceDiscriminator::GoogleTts;
    }

    public function getMaxCharsPerRequest(): int
    {
        return self::MAX_CHARS;
    }

    /**
     * @throws TtsProviderException
     */
    public function precheck(Voice $voice, ExtSystem $extSystem): void
    {
        $voice instanceof GoogleTtsVoice || throw new TtsProviderException(sprintf('Expected %s, got %s.', GoogleTtsVoice::class, $voice::class));

        // getClient() reads + parses the service-account JSON keyfile and configures the Google
        // client. Deterministic from filesystem state, no HTTP. Cached per ExtSystem so the
        // orchestrator that runs later doesn't pay the cost twice.
        $this->authClientProvider->getClient($extSystem->getSlug());
        $this->resolveChunkStorageName($extSystem);
    }

    /**
     * @throws TtsProviderException
     * @throws FilesystemException
     */
    public function synthesize(string $text, Voice $voice, ExtSystem $extSystem): AdapterFile
    {
        $this->precheck($voice, $extSystem);
        assert($voice instanceof GoogleTtsVoice);

        $chunks = $this->chunker->chunk($text, $this->resolveChunkSize());
        if ([] === $chunks) {
            throw new TtsProviderException('Cannot synthesize empty text.');
        }

        $accessToken = $this->getAccessToken($extSystem);
        $languageCode = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->languageCode;

        if (1 === count($chunks)) {
            return $this->writeSingleChunk(
                $this->extractAudio($this->ttsClient->synthesize($accessToken, $this->buildBody($chunks[0], $voice, $languageCode))),
            );
        }

        return $this->concatChunks($this->synthesizeChunks($chunks, $voice, $accessToken, $languageCode));
    }

    /**
     * @param list<string> $chunks
     *
     * @return Generator<string>
     *
     * @throws TtsProviderException
     */
    private function synthesizeChunks(array $chunks, GoogleTtsVoice $voice, string $accessToken, string $languageCode): Generator
    {
        foreach ($chunks as $chunk) {
            yield $this->extractAudio($this->ttsClient->synthesize($accessToken, $this->buildBody($chunk, $voice, $languageCode)));
        }
    }

    /**
     * @throws TtsProviderException
     */
    private function extractAudio(HttpClientResponse $response): string
    {
        if ($response->hasError()) {
            throw new TtsProviderException(sprintf('Google TTS API returned HTTP %d.', $response->getStatusCode()));
        }

        try {
            $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TtsProviderException('Google TTS response is not valid JSON: ' . $e->getMessage(), 0, $e);
        }
        $audioContent = is_array($data) ? ($data[self::RESPONSE_KEY_AUDIO_CONTENT] ?? null) : null;
        if (null === $audioContent) {
            throw new TtsProviderException('Google TTS response missing audioContent.');
        }

        $decoded = base64_decode((string) $audioContent, strict: true);
        if (false === $decoded) {
            throw new TtsProviderException('Google TTS response has invalid base64 audioContent.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(string $text, GoogleTtsVoice $voice, string $languageCode): array
    {
        return [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $languageCode,
                'name' => $voice->getExternalVoiceId(),
                'ssmlGender' => $voice->getSsmlGender()->value,
            ],
            'audioConfig' => [
                'audioEncoding' => self::AUDIO_ENCODING_MP3,
                'speakingRate' => $voice->getSpeakingRate(),
                'pitch' => $voice->getPitch(),
            ],
        ];
    }

    /**
     * @throws TtsProviderException
     */
    private function getAccessToken(ExtSystem $extSystem): string
    {
        try {
            $token = $this->authClientProvider->getClient($extSystem->getSlug())->fetchAccessTokenWithAssertion();
        } catch (GoogleException $e) {
            throw new TtsProviderException(sprintf(
                'Google TTS token fetch failed for ExtSystem "%s": %s',
                $extSystem->getSlug(),
                $e->getMessage(),
            ), 0, $e);
        }

        if (false === isset($token[self::RESPONSE_KEY_ACCESS_TOKEN])) {
            throw new TtsProviderException(sprintf(
                'Google TTS token response missing access_token (ExtSystem "%s").',
                $extSystem->getSlug(),
            ));
        }

        return (string) $token[self::RESPONSE_KEY_ACCESS_TOKEN];
    }
}
