<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsAuthClientProvider;
use AnzuSystems\CoreDamBundle\Domain\Tts\HttpClient\GoogleTtsClient;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\FileSystem\FileSystemProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\Provider\GoogleSynthesizeRequestDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\TtsChunkSynthesisResult;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Google\Exception as GoogleException;
use JsonException;

/** Google Cloud TTS; stateless across chunks — one chunk = one HTTP call. */
final class GoogleTtsProvider extends AbstractTtsProvider
{
    // Google's hard per-request ceiling; intentionally independent of ElevenlabsTtsProvider::MAX_CHARS.
    private const int MAX_CHARS = 5_000;
    private const string RESPONSE_KEY_AUDIO_CONTENT = 'audioContent';
    private const string RESPONSE_KEY_ACCESS_TOKEN = 'access_token';

    public function __construct(
        private readonly GoogleTtsClient $ttsClient,
        private readonly GoogleTtsAuthClientProvider $authClientProvider,
        ExtSystemConfigurationProvider $extSystemConfigProvider,
        FileSystemProvider $fileSystemProvider,
    ) {
        parent::__construct($fileSystemProvider, $extSystemConfigProvider);
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

        // getClient() parses the service-account keyfile (no HTTP) and caches the client per ExtSystem.
        $this->authClientProvider->getClient($extSystem->getSlug());
        $this->assertTtsConfiguration($extSystem);
    }

    /**
     * @param list<string> $previousRequestIds ignored (Google is stateless)
     *
     * @throws TtsProviderException
     */
    public function synthesizeChunk(string $text, Voice $voice, ExtSystem $extSystem, array $previousRequestIds): TtsChunkSynthesisResult
    {
        $voice instanceof GoogleTtsVoice || throw new TtsProviderException(sprintf('Expected %s, got %s.', GoogleTtsVoice::class, $voice::class));

        $languageCode = $voice->getVoiceFamily()->getLanguage()->getBcpLocale();
        $bytes = $this->extractAudio(
            $this->ttsClient->synthesize($this->getAccessToken($extSystem), $this->buildRequest($text, $voice, $languageCode)),
        );

        return new TtsChunkSynthesisResult($bytes, null);
    }

    /**
     * @throws TtsProviderException
     */
    private function extractAudio(HttpClientResponse $response): string
    {
        if ($response->hasError()) {
            throw TtsProviderException::fromHttpStatus(
                $response->getStatusCode(),
                sprintf('Google TTS API returned HTTP %d.', $response->getStatusCode()),
            );
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

    private function buildRequest(string $text, GoogleTtsVoice $voice, string $languageCode): GoogleSynthesizeRequestDto
    {
        $request = new GoogleSynthesizeRequestDto();
        $request->getInput()->setText($text);
        $request->getVoice()
            ->setLanguageCode($languageCode)
            ->setName($voice->getExternalVoiceId())
            ->setSsmlGender($voice->getSsmlGender());
        $request->getAudioConfig()
            ->setSpeakingRate($voice->getSpeakingRate())
            ->setPitch($voice->getPitch());

        return $request;
    }

    /**
     * @throws TtsProviderException
     */
    private function getAccessToken(ExtSystem $extSystem): string
    {
        try {
            $token = $this->authClientProvider->getClient($extSystem->getSlug())->fetchAccessTokenWithAssertion();
        } catch (GoogleException $e) {
            throw TtsProviderException::transient(sprintf(
                'Google TTS token fetch failed for ExtSystem "%s": %s',
                $extSystem->getSlug(),
                $e->getMessage(),
            ), $e);
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
