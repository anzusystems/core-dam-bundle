<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\Voice;

final class VoiceManager extends AbstractManager
{
    public function create(Voice $voice, bool $flush = true): Voice
    {
        $this->trackCreation($voice);
        $this->entityManager->persist($voice);
        $this->flush($flush);

        return $voice;
    }

    public function update(Voice $voice, bool $flush = true): Voice
    {
        $this->trackModification($voice);
        $this->flush($flush);

        return $voice;
    }

    public function delete(Voice $voice, bool $flush = true): bool
    {
        $this->entityManager->remove($voice);
        $this->flush($flush);

        return true;
    }
}
