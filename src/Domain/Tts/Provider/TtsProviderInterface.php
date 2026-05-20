<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\VoiceDiscriminator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface TtsProviderInterface
{
    public static function getDefaultKeyName(): string;

    public function getName(): VoiceDiscriminator;

    /**
     * Returns the synthesized MP3 as an {@see AdapterFile} on the tmp filesystem — caller streams
     * from disk to permanent storage to avoid holding full-length audio in memory.
     *
     * @throws TtsProviderException
     */
    public function synthesize(string $text, Voice $voice, ExtSystem $extSystem): AdapterFile;

    /**
     * Deterministic, non-HTTP credential + config check — throws if {@see self::synthesize}
     * would fail purely due to missing/malformed tenant configuration. Called at dispatch time
     * so misconfigured tenants never persist a {@see \AnzuSystems\CoreDamBundle\Entity\TtsNarrationRequest}.
     * MUST NOT make network calls or check anything that can change between dispatch and processing.
     *
     * @throws TtsProviderException
     */
    public function precheck(Voice $voice, ExtSystem $extSystem): void;

    public function getMaxCharsPerRequest(): int;
}
