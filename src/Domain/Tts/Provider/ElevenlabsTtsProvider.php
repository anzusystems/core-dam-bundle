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
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider\ElevenlabsSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\TtsChunkSynthesisResult;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;

/** ElevenLabs TTS; previous_request_ids threads cross-splice prosody; ffmpeg mux required (VBR header). */
final class ElevenlabsTtsProvider extends AbstractTtsProvider
{
    // ElevenLabs hard per-request ceiling; intentionally independent of GoogleTtsProvider::MAX_CHARS.
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
            $this->buildRequest($text, $previousRequestIds, $voice),
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

        throw TtsProviderException::fromHttpStatus($response->getStatusCode(), sprintf(
            'ElevenLabs API returned HTTP %d. Body: %s',
            $response->getStatusCode(),
            $bodyExcerpt,
        ));
    }

    /**
     * @param list<string> $previousRequestIds
     */
    private function buildRequest(string $text, array $previousRequestIds, ElevenlabsVoice $voice): ElevenlabsSynthesizeRequestDto
    {
        $request = (new ElevenlabsSynthesizeRequestDto())
            ->setText($text)
            ->setModelId($voice->getModelId());
        $request->getVoiceSettings()
            ->setStability($voice->getStability())
            ->setSimilarityBoost($voice->getSimilarityBoost());
        if ([] !== $previousRequestIds) {
            $request->setPreviousRequestIds($previousRequestIds);
        }

        return $request;
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
