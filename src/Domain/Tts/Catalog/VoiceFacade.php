<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Traits\ValidatorAwareTrait;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @use TtsCrudFacadeTrait<Voice>
 */
final class VoiceFacade
{
    use ValidatorAwareTrait;

    /** @use TtsCrudFacadeTrait<Voice> */
    use TtsCrudFacadeTrait;

    public function __construct(
        private readonly VoiceManager $voiceManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Delegates full update (field copy + per-kind specifics + tracking) to the per-kind manager resolved
     * by discriminator. VoiceFamily binding + discriminator are immutable post-create.
     *
     * @param Voice $entity    existing persistent entity
     * @param Voice $newEntity incoming (deserialized) entity carrying new values
     *
     * @throws ValidationException
     */
    public function update(object $entity, object $newEntity): object
    {
        App::throwOnReadOnlyMode();
        /** @var Voice $entity */
        /** @var Voice $newEntity */
        $this->voiceManager->applyIncoming($entity, $newEntity);
        $this->validator->validate($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @return TtsCrudManagerInterface<Voice>
     */
    protected function manager(): TtsCrudManagerInterface
    {
        return $this->voiceManager;
    }

    /**
     * Not used — update() is overridden above and calls applyIncoming directly.
     */
    protected function applyUpdate(object $existing, object $incoming): void
    {
    }
}
