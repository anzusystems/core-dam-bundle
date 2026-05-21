<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\TtsAsset;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;
use AnzuSystems\CoreDamBundle\Exception\DependencyExistsException;
use AnzuSystems\CoreDamBundle\Repository\TtsAssetRepository;

/**
 * @implements TtsCrudManagerInterface<VoiceFamily>
 */
final class VoiceFamilyManager extends AbstractManager implements TtsCrudManagerInterface
{
    public function __construct(
        private readonly TtsAssetRepository $ttsAssetRepository,
    ) {
    }

    /**
     * @param VoiceFamily $entity
     */
    public function create(object $entity, bool $flush = true): VoiceFamily
    {
        $this->trackCreation($entity);
        $this->entityManager->persist($entity);
        $this->flush($flush);

        return $entity;
    }

    /**
     * @param VoiceFamily $entity
     */
    public function update(object $entity, bool $flush = true): VoiceFamily
    {
        $this->trackModification($entity);
        $this->flush($flush);

        return $entity;
    }

    /**
     * Pre-checks TtsAsset references — surfaces a translatable validation error to the admin
     * instead of a raw FK violation. Voice rows CASCADE-delete with the family — not user-visible.
     *
     * @param VoiceFamily $entity
     *
     * @throws DependencyExistsException
     */
    public function delete(object $entity, bool $flush = true): bool
    {
        if ($this->ttsAssetRepository->existsByVoiceFamily($entity)) {
            throw (new DependencyExistsException())->addDependency(TtsAsset::class);
        }

        $this->entityManager->remove($entity);
        $this->flush($flush);

        return true;
    }
}
