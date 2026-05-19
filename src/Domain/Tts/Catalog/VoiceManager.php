<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Voice;

/**
 * @implements TtsCrudManagerInterface<Voice>
 */
final class VoiceManager extends AbstractManager implements TtsCrudManagerInterface
{
    /**
     * @param Voice $entity
     */
    public function create(object $entity, bool $flush = true): Voice
    {
        $this->trackCreation($entity);
        $this->entityManager->persist($entity);
        $this->flush($flush);

        return $entity;
    }

    /**
     * @param Voice $entity
     */
    public function update(object $entity, bool $flush = true): Voice
    {
        $this->trackModification($entity);
        $this->flush($flush);

        return $entity;
    }

    /**
     * @param Voice $entity
     */
    public function delete(object $entity, bool $flush = true): bool
    {
        $this->entityManager->remove($entity);
        $this->flush($flush);

        return true;
    }
}
