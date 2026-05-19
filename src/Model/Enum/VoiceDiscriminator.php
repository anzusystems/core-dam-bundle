<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\ElevenlabsVoiceManager;
use AnzuSystems\CoreDamBundle\Domain\Tts\Catalog\GoogleTtsVoiceManager;
use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;

enum VoiceDiscriminator: string implements EnumInterface
{
    use BaseEnumTrait;

    case Elevenlabs = 'elevenlabs';
    case GoogleTts = 'google_tts';

    public const VoiceDiscriminator Default = self::Elevenlabs;

    public const array MAP = [
        self::Elevenlabs->value => ElevenlabsVoice::class,
        self::GoogleTts->value => GoogleTtsVoice::class,
    ];

    public function getManagerClass(): string
    {
        return match ($this) {
            self::Elevenlabs => ElevenlabsVoiceManager::class,
            self::GoogleTts => GoogleTtsVoiceManager::class,
        };
    }
}
