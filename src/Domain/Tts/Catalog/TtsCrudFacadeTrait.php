<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Domain\AbstractManager;

/**
 * Shared CRUD facade skeleton for TTS aggregates (Voice, VoiceFamily). Each user supplies the
 * concrete manager + the field-copy logic for the update path; create/delete are uniform.
 *
 * @template TEntity of object
 */
trait TtsCrudFacadeTrait
{
    use ValidatorAwareTrait;

    /**
     * @phpstan-return AbstractManager
     */
    abstract protected function manager(): AbstractManager;

    /**
     * @param TEntity $existing
     * @param TEntity $incoming
     */
    abstract protected function applyUpdate(object $existing, object $incoming): void;

    /**
     * @param TEntity $entity
     *
     * @return TEntity
     *
     * @throws ValidationException
     */
    public function create(object $entity): object
    {
        App::throwOnReadOnlyMode();
        $this->validator->validate($entity);
        $this->manager()->create($entity);

        return $entity;
    }

    /**
     * @param TEntity $entity
     * @param TEntity $newEntity
     *
     * @return TEntity
     *
     * @throws ValidationException
     */
    public function update(object $entity, object $newEntity): object
    {
        App::throwOnReadOnlyMode();
        $this->applyUpdate($entity, $newEntity);
        $this->validator->validate($entity);
        $this->manager()->update($entity);

        return $entity;
    }

    /**
     * @param TEntity $entity
     */
    public function delete(object $entity): bool
    {
        App::throwOnReadOnlyMode();

        return $this->manager()->delete($entity);
    }
}
