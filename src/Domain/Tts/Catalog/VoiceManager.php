<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\Tts\Catalog;

use AnzuSystems\CoreDamBundle\Entity\Voice;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class VoiceManager
{
    public function __construct(
        #[AutowireLocator(AbstractVoiceManager::class)]
        private ServiceLocator $managers,
    ) {
    }

    public function create(Voice $voice, bool $flush = true): Voice
    {
        return $this->getManager($voice)->create($voice, $flush);
    }

    public function update(Voice $voice, Voice $newVoice, bool $flush = true): Voice
    {
        return $this->getManager($voice)->update($voice, $newVoice, $flush);
    }

    public function delete(Voice $voice, bool $flush = true): bool
    {
        return $this->getManager($voice)->delete($voice, $flush);
    }

    private function getManager(Voice $voice): AbstractVoiceManager
    {
        return $this->managers->get($voice->getDiscriminator()->getManagerClass());
    }
}
