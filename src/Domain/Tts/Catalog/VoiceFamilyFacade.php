<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;

/**
 * @use TtsCrudFacadeTrait<VoiceFamily>
 */
final class VoiceFamilyFacade
{
    /** @use TtsCrudFacadeTrait<VoiceFamily> */
    use TtsCrudFacadeTrait;

    public function __construct(
        private readonly VoiceFamilyManager $voiceFamilyManager,
    ) {
    }

    /**
     * @return TtsCrudManagerInterface<VoiceFamily>
     */
    protected function manager(): TtsCrudManagerInterface
    {
        return $this->voiceFamilyManager;
    }

    /**
     * Slug + extSystem are intentionally excluded — VoiceFamily slugs are forever-stable identifiers.
     */
    protected function applyUpdate(object $existing, object $incoming): void
    {
        /** @var VoiceFamily $existing */
        /** @var VoiceFamily $incoming */
        $existing
            ->setDisplayName($incoming->getDisplayName())
            ->setLanguage($incoming->getLanguage())
            ->setPreferredProvider($incoming->getPreferredProvider())
            ->setActive($incoming->isActive())
        ;
    }
}
