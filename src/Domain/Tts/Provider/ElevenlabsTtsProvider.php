<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\ElevenlabsClient;
use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\TtsChunkSynthesisResult;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;

/**
 * ElevenLabs TTS. One chunk = one HTTP call; the caller threads `previous_request_ids` (oldest-first,
 * ≤3) for cross-splice prosody. Multi-chunk MP3 is muxed via ffmpeg at assemble time — raw-byte concat
 * would leave the Xing/LAME VBR header pointing at the first chunk only, breaking duration/seek.
 */
final class ElevenlabsTtsProvider extends AbstractTtsProvider
{
    // ElevenLabs hard per-request ceiling (documented API limit). The effective chunk size is the
    // operator-driven Config::chunkSizeChars clamped to this in the pipeline.
    // Intentionally independent of GoogleTtsProvider::MAX_CHARS — do not merge into a shared constant.
    private const int MAX_CHARS = 5_000;
    private const int ERROR_BODY_EXCERPT_LIMIT = 500;

    public function __construct(
        private readonly ElevenlabsClient $client,
        ExtSystemConfigurationProvider $extSystemConfigProvider,
        FileSystemProvider $fileSystemProvider,
    ) {
        parent::__construct($fileSystemProvider, $extSystemConfigProvider);
    }

    public static function getDefaultKeyName(): string
    {
        return VoiceDiscriminator::Elevenlabs->value;
    }

    public function getName(): VoiceDiscriminator
    {
        return VoiceDiscriminator::Elevenlabs;
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
        $voice instanceof ElevenlabsVoice || throw new TtsProviderException(sprintf('Expected %s, got %s.', ElevenlabsVoice::class, $voice::class));

        $this->resolveApiKey($extSystem);
        $this->assertChunkStorageConfigured($extSystem);
    }

    /**
     * @param list<string> $previousRequestIds
     *
     * @throws TtsProviderException
     */
    public function synthesizeChunk(string $text, Voice $voice, ExtSystem $extSystem, array $previousRequestIds): TtsChunkSynthesisResult
    {
        $voice instanceof ElevenlabsVoice || throw new TtsProviderException(sprintf('Expected %s, got %s.', ElevenlabsVoice::class, $voice::class));

        $result = $this->client->synthesize(
            $voice->getExternalVoiceId(),
            $this->resolveApiKey($extSystem),
            $this->buildBody($text, $previousRequestIds, $voice),
        );
        $this->assertSuccess($result->http);

        return new TtsChunkSynthesisResult($result->http->getContent(), $result->requestId);
    }

    private function assertSuccess(HttpClientResponse $response): void
    {
        if (false === $response->hasError()) {
            return;
        }

        $body = (string) $response->getContent();
        $bodyExcerpt = '' === $body ? '<empty>' : StringHelper::parseLength($body, self::ERROR_BODY_EXCERPT_LIMIT);

        throw new TtsProviderException(sprintf(
            'ElevenLabs API returned HTTP %d. Body: %s',
            $response->getStatusCode(),
            $bodyExcerpt,
        ));
    }

    /**
     * @param list<string> $previousRequestIds
     *
     * @return array<string, mixed>
     */
    private function buildBody(string $text, array $previousRequestIds, ElevenlabsVoice $voice): array
    {
        $body = [
            'text' => $text,
            'model_id' => $voice->getModelId(),
            'voice_settings' => [
                'stability' => $voice->getStability(),
                'similarity_boost' => $voice->getSimilarityBoost(),
            ],
        ];
        if ([] !== $previousRequestIds) {
            $body['previous_request_ids'] = $previousRequestIds;
        }

        return $body;
    }

    /**
     * @throws TtsProviderException
     */
    private function resolveApiKey(ExtSystem $extSystem): string
    {
        $apiKey = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->elevenlabsApiKey;
        if ('' === $apiKey) {
            throw new TtsProviderException(sprintf('No ElevenLabs API key configured for ExtSystem "%s".', $extSystem->getSlug()));
        }

        return $apiKey;
    }
}
