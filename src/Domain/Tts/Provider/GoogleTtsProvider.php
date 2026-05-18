<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsAuthClientProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsClient;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
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
    private const int MAX_CHARS = 5_000;
    private const string AUDIO_ENCODING_MP3 = 'MP3';
    private const string RESPONSE_KEY_AUDIO_CONTENT = 'audioContent';
    private const string RESPONSE_KEY_ACCESS_TOKEN = 'access_token';

    public function __construct(
        private readonly GoogleTtsClient $ttsClient,
        private readonly GoogleTtsAuthClientProvider $authClientProvider,
        private readonly TextChunker $chunker,
        private readonly ExtSystemConfigurationProvider $extSystemConfigProvider,
        FileSystemProvider $fileSystemProvider,
        FfmpegService $ffmpegService,
    ) {
        parent::__construct($fileSystemProvider, $ffmpegService);
    }

    public static function getDefaultKeyName(): string
    {
        return TtsProvider::GoogleTts->value;
    }

    public function getName(): TtsProvider
    {
        return TtsProvider::GoogleTts;
    }

    public function getMaxCharsPerRequest(): int
    {
        return self::MAX_CHARS;
    }

    /**
     * @throws TtsProviderException
     * @throws FilesystemException
     */
    public function synthesize(string $text, string $externalVoiceId, ExtSystem $extSystem): AdapterFile
    {
        $chunks = $this->chunker->chunk($text, self::MAX_CHARS);
        if ([] === $chunks) {
            throw new TtsProviderException('Cannot synthesize empty text.');
        }

        $accessToken = $this->getAccessToken($extSystem);
        $languageCode = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->languageCode;

        if (1 === count($chunks)) {
            return $this->writeSingleChunk(
                $this->extractAudio($this->ttsClient->synthesize($accessToken, $this->buildBody($chunks[0], $externalVoiceId, $languageCode))),
            );
        }

        return $this->concatChunks($this->synthesizeChunks($chunks, $externalVoiceId, $accessToken, $languageCode));
    }

    /**
     * @param list<string> $chunks
     *
     * @return Generator<string>
     *
     * @throws TtsProviderException
     */
    private function synthesizeChunks(array $chunks, string $externalVoiceId, string $accessToken, string $languageCode): Generator
    {
        foreach ($chunks as $chunk) {
            yield $this->extractAudio($this->ttsClient->synthesize($accessToken, $this->buildBody($chunk, $externalVoiceId, $languageCode)));
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
    private function buildBody(string $text, string $externalVoiceId, string $languageCode): array
    {
        return [
            'input' => ['text' => $text],
            'voice' => ['languageCode' => $languageCode, 'name' => $externalVoiceId],
            'audioConfig' => ['audioEncoding' => self::AUDIO_ENCODING_MP3],
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
