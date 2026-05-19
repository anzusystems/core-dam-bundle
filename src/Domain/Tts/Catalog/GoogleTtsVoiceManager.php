<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\GoogleTtsVoice;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use LogicException;

final class GoogleTtsVoiceManager extends AbstractVoiceManager
{
    protected function setSpecifics(Voice $existing, Voice $incoming): void
    {
        $existing instanceof GoogleTtsVoice || throw new LogicException(sprintf('Expected %s, got %s.', GoogleTtsVoice::class, $existing::class));
        $incoming instanceof GoogleTtsVoice || throw new LogicException(sprintf('Expected %s, got %s.', GoogleTtsVoice::class, $incoming::class));

        $existing
            ->setSsmlGender($incoming->getSsmlGender())
            ->setSpeakingRate($incoming->getSpeakingRate())
            ->setPitch($incoming->getPitch())
        ;
    }
}
