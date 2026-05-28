<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Voice;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
abstract class AbstractVoiceManager extends AbstractManager
{
    public function create(Voice $voice, bool $flush = true): Voice
    {
        $this->trackCreation($voice);
        $this->entityManager->persist($voice);
        $this->flush($flush);

        return $voice;
    }

    public function update(Voice $existing, Voice $incoming, bool $flush = true): Voice
    {
        $this->applyUpdate($existing, $incoming);
        $this->flush($flush);

        return $existing;
    }

    public function delete(Voice $voice, bool $flush = true): bool
    {
        $this->entityManager->remove($voice);
        $this->flush($flush);

        return true;
    }

    abstract protected function setSpecifics(Voice $existing, Voice $incoming): void;

    /**
     * Copies shared + per-kind fields and tracks modification — does not flush.
     */
    private function applyUpdate(Voice $existing, Voice $incoming): void
    {
        $this->trackModification($existing);
        $existing
            ->setExternalVoiceId($incoming->getExternalVoiceId())
            ->setMain($incoming->isMain())
            ->setActive($incoming->isActive())
        ;
        $this->setSpecifics($existing, $incoming);
    }
}
