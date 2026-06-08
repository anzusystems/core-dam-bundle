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
     * @param list<string> $previousRequestIds oldest-first, ≤3; ElevenLabs prosody hint; Google ignores it
     *
     * @throws TtsProviderException
     */
    public function synthesizeChunk(string $text, Voice $voice, ExtSystem $extSystem, array $previousRequestIds): TtsChunkSynthesisResult;

    /**
     * Credential + config check (no network calls); called at dispatch time.
     *
     * @throws TtsProviderException
     */
    public function precheck(Voice $voice, ExtSystem $extSystem): void;

    public function getMaxCharsPerRequest(): int;
}
