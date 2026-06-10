<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\ElevenlabsVoice;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use LogicException;

final class ElevenlabsVoiceManager extends AbstractVoiceManager
{
    protected function setSpecifics(Voice $existing, Voice $incoming): void
    {
        $existing instanceof ElevenlabsVoice || throw new LogicException(sprintf('Expected %s, got %s.', ElevenlabsVoice::class, $existing::class));
        $incoming instanceof ElevenlabsVoice || throw new LogicException(sprintf('Expected %s, got %s.', ElevenlabsVoice::class, $incoming::class));

        $existing
            ->setModelId($incoming->getModelId())
            ->setStability($incoming->getStability())
            ->setSimilarityBoost($incoming->getSimilarityBoost())
        ;
    }
}
