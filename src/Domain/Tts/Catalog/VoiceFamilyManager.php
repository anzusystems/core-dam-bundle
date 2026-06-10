<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;

final class VoiceFamilyManager extends AbstractManager
{
    public function create(VoiceFamily $voiceFamily, bool $flush = true): VoiceFamily
    {
        $this->trackCreation($voiceFamily);
        $this->entityManager->persist($voiceFamily);
        $this->flush($flush);

        return $voiceFamily;
    }

    /**
     * Slug + extSystem are immutable and intentionally not copied.
     */
    public function update(VoiceFamily $voiceFamily, VoiceFamily $newVoiceFamily, bool $flush = true): VoiceFamily
    {
        $this->trackModification($voiceFamily);
        $voiceFamily
            ->setDisplayName($newVoiceFamily->getDisplayName())
            ->setLanguage($newVoiceFamily->getLanguage())
            ->setPreferredProvider($newVoiceFamily->getPreferredProvider())
            ->setActive($newVoiceFamily->isActive())
        ;
        $this->colUpdate($voiceFamily->getKeywords(), $newVoiceFamily->getKeywords());
        $this->flush($flush);

        return $voiceFamily;
    }

    public function delete(VoiceFamily $voiceFamily, bool $flush = true): bool
    {
        $this->entityManager->remove($voiceFamily);
        $this->flush($flush);

        return true;
    }
}
