<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

/**
 * Shared persistence contract for the Voice + VoiceFamily managers. Lets {@see TtsCrudFacadeTrait}
 * call create/update/delete without knowing the concrete manager type.
 *
 * @template TEntity of object
 */
interface TtsCrudManagerInterface
{
    /**
     * @param TEntity $entity
     *
     * @return TEntity
     */
    public function create(object $entity, bool $flush = true): object;

    /**
     * @param TEntity $entity
     *
     * @return TEntity
     */
    public function update(object $entity, bool $flush = true): object;

    /**
     * @param TEntity $entity
     */
    public function delete(object $entity, bool $flush = true): bool;
}
