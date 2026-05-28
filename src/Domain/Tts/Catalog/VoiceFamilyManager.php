<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\DependencyExistsException;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;

final class VoiceFamilyManager extends AbstractManager
{
    public function __construct(
        private readonly TtsAssetRepository $ttsAssetRepository,
    ) {
    }

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
            ->setKeyword($newVoiceFamily->getKeyword())
        ;
        $this->colUpdate($voiceFamily->getKeywords(), $newVoiceFamily->getKeywords());
        $this->flush($flush);

        return $voiceFamily;
    }

    /**
     * Surfaces a translatable error instead of a raw FK violation when TtsAssets still reference the family.
     *
     * @throws DependencyExistsException
     */
    public function delete(VoiceFamily $voiceFamily, bool $flush = true): bool
    {
        if ($this->ttsAssetRepository->existsByVoiceFamily($voiceFamily)) {
            throw (new DependencyExistsException())->addDependency(TtsAsset::class);
        }

        $this->entityManager->remove($voiceFamily);
        $this->flush($flush);

        return true;
    }
}
