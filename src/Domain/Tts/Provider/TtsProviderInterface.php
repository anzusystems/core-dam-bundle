<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Provider;

use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Exception\TtsProviderException;
use AnzuSystems\CoreDamBundle\Model\Dto\File\AdapterFile;
use AnzuSystems\CoreDamBundle\Model\Enum\TtsProvider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface TtsProviderInterface
{
    public static function getDefaultKeyName(): string;

    public function getName(): TtsProvider;

    /**
     * Returns the synthesized MP3 as an {@see AdapterFile} on the tmp filesystem — caller streams
     * from disk to permanent storage to avoid holding full-length audio in memory.
     *
     * @throws TtsProviderException
     */
    public function synthesize(string $text, string $externalVoiceId, ExtSystem $extSystem): AdapterFile;

    public function getMaxCharsPerRequest(): int;
}
