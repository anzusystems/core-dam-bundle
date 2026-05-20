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
use AnzuSystems\CoreDamBundle\Ffmpeg\FfmpegService;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Helper\StringHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Generator;
use League\Flysystem\FilesystemException;

/**
 * ElevenLabs TTS. Chains `previous_request_ids` across chunks for cross-splice prosody. Multi-chunk
 * MP3 is muxed via ffmpeg — raw-byte concat would leave the Xing/LAME VBR header pointing at the
 * first chunk only, breaking duration/seek for many players.
 */
final class ElevenlabsTtsProvider extends AbstractTtsProvider
{
    // ElevenLabs per-request limit. Intentionally independent of GoogleTtsProvider::MAX_CHARS
    // — do not merge into a shared constant.
    private const int MAX_CHARS = 5_000;
    private const int REQUEST_ID_CHAIN_LIMIT = 3;
    private const int ERROR_BODY_EXCERPT_LIMIT = 500;

    public function __construct(
        private readonly ElevenlabsClient $client,
        private readonly TextChunker $chunker,
        ExtSystemConfigurationProvider $extSystemConfigProvider,
        FileSystemProvider $fileSystemProvider,
        FfmpegService $ffmpegService,
    ) {
        parent::__construct($fileSystemProvider, $ffmpegService, $extSystemConfigProvider);
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
        $this->resolveChunkStorageName($extSystem);
    }

    /**
     * @throws TtsProviderException
     * @throws FilesystemException
     */
    public function synthesize(string $text, Voice $voice, ExtSystem $extSystem): AdapterFile
    {
        $this->precheck($voice, $extSystem);
        assert($voice instanceof ElevenlabsVoice);

        $chunks = $this->chunker->chunk($text, self::MAX_CHARS);
        if ([] === $chunks) {
            throw new TtsProviderException('Cannot synthesize empty text.');
        }

        $apiKey = $this->resolveApiKey($extSystem);

        if (1 === count($chunks)) {
            $result = $this->client->synthesize($voice->getExternalVoiceId(), $apiKey, $this->buildBody($chunks[0], [], $voice));
            $this->assertSuccess($result->http);

            return $this->writeSingleChunk($result->http->getContent());
        }

        return $this->concatChunks($this->synthesizeChunks($chunks, $voice, $apiKey));
    }

    /**
     * Yields raw MP3 bytes per chunk, threading `previous_request_ids` across calls.
     *
     * @param list<string> $chunks
     *
     * @return Generator<string>
     *
     * @throws TtsProviderException
     */
    private function synthesizeChunks(array $chunks, ElevenlabsVoice $voice, string $apiKey): Generator
    {
        $previousRequestIds = [];

        foreach ($chunks as $chunk) {
            $result = $this->client->synthesize(
                $voice->getExternalVoiceId(),
                $apiKey,
                $this->buildBody($chunk, $previousRequestIds, $voice),
            );
            $this->assertSuccess($result->http);

            yield $result->http->getContent();

            if (null !== $result->requestId) {
                $previousRequestIds[] = $result->requestId;
                if (count($previousRequestIds) > self::REQUEST_ID_CHAIN_LIMIT) {
                    $previousRequestIds = array_slice($previousRequestIds, -self::REQUEST_ID_CHAIN_LIMIT);
                }
            }
        }
    }

    /**
     * @throws TtsProviderException
     */
    private function resolveApiKey(ExtSystem $extSystem): string
    {
        // extSystemConfigProvider is inherited from AbstractTtsProvider — single injection point.
        $apiKey = $this->extSystemConfigProvider->getTtsExtSystemConfiguration($extSystem->getSlug())->elevenlabsApiKey;
        if ('' === $apiKey) {
            throw new TtsProviderException(sprintf(
                'No ElevenLabs API key configured for ExtSystem "%s".',
                $extSystem->getSlug(),
            ));
        }

        return $apiKey;
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
}
