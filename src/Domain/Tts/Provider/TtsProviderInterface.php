<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Dto\Tts\TtsChunkSynthesisResult;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface TtsProviderInterface
{
    public static function getDefaultKeyName(): string;

    public function getName(): VoiceDiscriminator;

    /**
     * Synthesises ONE chunk (≤ {@see self::getMaxCharsPerRequest()} chars) into raw MP3 bytes.
     * Chunking, chunk-storage persistence and ffmpeg concat live one level up in the pipeline so each
     * chunk can run in its own worker message. `$previousRequestIds` threads ElevenLabs prosody across
     * the splice (oldest-first, ≤3); the stateless Google provider ignores it.
     *
     * @param list<string> $previousRequestIds
     *
     * @throws TtsProviderException
     */
    public function synthesizeChunk(string $text, Voice $voice, ExtSystem $extSystem, array $previousRequestIds): TtsChunkSynthesisResult;

    /**
     * Deterministic, non-HTTP credential + config check — throws if synthesis would fail purely due to
     * missing/malformed tenant configuration. Called at dispatch time so misconfigured tenants never
     * persist a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest}. MUST NOT make network calls.
     *
     * @throws TtsProviderException
     */
    public function precheck(Voice $voice, ExtSystem $extSystem): void;

    public function getMaxCharsPerRequest(): int;
}
