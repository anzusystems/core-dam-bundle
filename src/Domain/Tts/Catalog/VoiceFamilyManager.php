<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\VoiceFamily;

/**
 * @implements TtsCrudManagerInterface<VoiceFamily>
 */
final class VoiceFamilyManager extends AbstractManager implements TtsCrudManagerInterface
{
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
     * @param VoiceFamily $entity
     */
    public function delete(object $entity, bool $flush = true): bool
    {
        $this->entityManager->remove($entity);
        $this->flush($flush);

        return true;
    }
}
