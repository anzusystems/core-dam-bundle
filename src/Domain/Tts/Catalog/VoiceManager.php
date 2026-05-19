<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\Voice;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Dispatcher that resolves the correct per-discriminator manager from the service locator and delegates
 * CRUD operations to it. Implements {@see TtsCrudManagerInterface} so the interface contract is satisfied;
 * {@see VoiceFacade} overrides update() and calls {@see applyIncoming} directly for validation before flush.
 *
 * @implements TtsCrudManagerInterface<Voice>
 */
final readonly class VoiceManager implements TtsCrudManagerInterface
{
    public function __construct(
        #[AutowireLocator(AbstractVoiceManager::class)]
        private ServiceLocator $managers,
    ) {
    }

    /**
     * @param Voice $entity
     */
    public function create(object $entity, bool $flush = true): Voice
    {
        return $this->getManager($entity)->create($entity, $flush);
    }

    /**
     * Single-entity update — field copy already applied; just flush.
     * Not used by VoiceFacade (which overrides update() and calls applyIncoming instead).
     *
     * @param Voice $entity
     */
    public function update(object $entity, bool $flush = true): Voice
    {
        return $this->getManager($entity)->update($entity, $entity, $flush);
    }

    /**
     * @param Voice $entity
     */
    public function delete(object $entity, bool $flush = true): bool
    {
        return $this->getManager($entity)->delete($entity, $flush);
    }

    /**
     * Copies all shared + per-kind fields from incoming into existing and marks modification.
     * Does NOT flush — caller is responsible for validation + flush.
     */
    public function applyIncoming(Voice $existing, Voice $incoming): void
    {
        $this->getManager($existing)->applyUpdate($existing, $incoming);
    }

    private function getManager(Voice $voice): AbstractVoiceManager
    {
        $managerClass = $voice->getDiscriminator()->getManagerClass();

        return $this->managers->get($managerClass);
    }
}
