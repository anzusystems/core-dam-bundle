<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Voice;

/**
 * @use TtsCrudFacadeTrait<Voice>
 */
final class VoiceFacade
{
    use TtsCrudFacadeTrait;

    public function __construct(
        private readonly VoiceManager $voiceManager,
    ) {
    }

    protected function manager(): AbstractManager
    {
        return $this->voiceManager;
    }

    /**
     * VoiceFamily binding + provider are immutable post-create.
     */
    protected function applyUpdate(object $existing, object $incoming): void
    {
        /** @var Voice $existing */
        /** @var Voice $incoming */
        $existing
            ->setExternalVoiceId($incoming->getExternalVoiceId())
            ->setMetadata($incoming->getMetadata())
            ->setMain($incoming->isMain())
            ->setActive($incoming->isActive())
        ;
    }
}
